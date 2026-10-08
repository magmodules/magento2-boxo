/*
 * Copyright Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

import { execSync } from 'child_process';
import { shellQuote } from './Shell';

/** A `core_config_data` scope. `default` is the global fallback every other scope inherits from. */
export type ConfigScope = 'default' | 'websites' | 'stores';

/**
 * Database helper that wraps Docker exec + PDO boilerplate.
 * Eliminates repeated env.php → PDO connection setup across modules.
 */
export class MagentoDb {
  constructor(private container: string) {}

  /**
   * Run arbitrary PHP with $pdo pre-initialized as a PDO connection.
   * The PHP code should use $pdo and echo its result.
   */
  query(phpBody: string): string {
    const fullPhp = `
      foreach (['/var/www/html/app/etc/env.php', '/data/app/etc/env.php'] as $p) { if (file_exists($p)) { $env = include $p; break; } }
      $db = $env['db']['connection']['default'];
      $pdo = new PDO("mysql:host={$db['host']};dbname={$db['dbname']}", $db['username'], $db['password']);
      ${phpBody}
    `;

    // Single-quoted, not `bash -lc`: the PHP is full of `$pdo`, `$env` and `$db`, and every layer
    // of shell in between would expand them into nothing. `shellQuote` is the only quoting here.
    return execSync(`docker exec ${this.container} php -r ${shellQuote(fullPhp)}`, {
      stdio: 'pipe',
      timeout: 30000,
      // The default 1 MB buffer turns a large result into a bare "spawnSync /bin/sh ENOBUFS",
      // which says nothing about the query that produced it.
      maxBuffer: 64 * 1024 * 1024,
    }).toString();
  }

  /**
   * Drop Magento's cached configuration so a row written here is actually read back.
   *
   * Writing core_config_data over SQL bypasses every cache Magento keeps in front of it, so
   * without this the application keeps serving the previous value and the spec asserts against
   * config it never applied. It fails as though the feature were broken — which cost one E2E
   * round on the BOXO key test, where deleting the API key still reported a working connection.
   */
  flushConfigCache(): void {
    execSync(`docker exec ${this.container} bash -lc ${JSON.stringify('bin/magento cache:flush config')}`, {
      stdio: 'pipe',
      timeout: 60000,
    });
  }

  /**
   * Set a config value in core_config_data (or delete the row if value is empty, which brings back
   * the config.xml default — it does not store an admin "No").
   */
  setConfig(path: string, value: string): void {
    this.setScopedConfig(path, value, 'default', 0);
  }

  /**
   * Set a config value in a specific scope.
   *
   * Worth reaching for deliberately: a value that exists only at website or store-view scope is
   * invisible to anything reading it from the admin, where the resolved store is the admin store
   * (id 0) and therefore falls back to the `default` row. Several modules have shipped an admin
   * controller that refused to run for exactly that reason while the storefront worked fine.
   */
  setScopedConfig(path: string, value: string, scope: ConfigScope, scopeId: number): void {
    if (value === '') {
      const b64Path = Buffer.from(path).toString('base64');
      this.query(`
        $stmt = $pdo->prepare("DELETE FROM core_config_data WHERE path = ? AND scope = ? AND scope_id = ?");
        $stmt->execute([base64_decode('${b64Path}'), '${scope}', ${scopeId}]);
        echo 'deleted';
      `);
      this.flushConfigCache();
      return;
    }

    const b64Path = Buffer.from(path).toString('base64');
    const b64Value = Buffer.from(value).toString('base64');
    this.query(`
      $path = base64_decode('${b64Path}');
      $value = base64_decode('${b64Value}');
      $stmt = $pdo->prepare("INSERT INTO core_config_data (scope, scope_id, path, value) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE value = ?");
      $stmt->execute(['${scope}', ${scopeId}, $path, $value, $value]);
      echo 'ok';
    `);
    this.flushConfigCache();
  }

  /**
   * Read a config value back out of a specific scope. Returns null when no row exists there —
   * which is not the same as the effective value, since Magento would fall back to `default`.
   */
  getScopedConfig(path: string, scope: ConfigScope = 'default', scopeId = 0): string | null {
    const b64Path = Buffer.from(path).toString('base64');
    const result = this.query(`
      $stmt = $pdo->prepare("SELECT value FROM core_config_data WHERE path = ? AND scope = ? AND scope_id = ?");
      $stmt->execute([base64_decode('${b64Path}'), '${scope}', ${scopeId}]);
      $row = $stmt->fetchColumn();
      echo $row === false ? '__NULL__' : $row;
    `).trim();

    return result === '__NULL__' ? null : result;
  }

  /**
   * Delete all config rows matching a LIKE pattern, in every scope, reverting to config.xml
   * defaults. Use between tests that write scoped config: a leftover website-scope row silently
   * changes the next test's answer.
   */
  resetConfig(pathPattern: string): void {
    const b64Pattern = Buffer.from(pathPattern).toString('base64');
    this.query(`
      $pattern = base64_decode('${b64Pattern}');
      $deleted = $pdo->exec("DELETE FROM core_config_data WHERE path LIKE " . $pdo->quote($pattern));
      echo "reset:$deleted";
    `);
    this.flushConfigCache();
  }
}
