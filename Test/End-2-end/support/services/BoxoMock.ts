/*
 * Copyright Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

import { execSync } from 'child_process';
import { moduleDir } from 'Helpers/Shell';

export const MOCK_PORT = 18092;
export const MOCK_BASE_URL = `http://127.0.0.1:${MOCK_PORT}`;
export const CONFIG_PATH_BASE_URL = 'magmodules_boxo/developer/api_base_url';
export const CONFIG_PATH_API_KEY = 'magmodules_boxo/general/api_key';

/** The key the mock server accepts. Anything else comes back 401, which is the point. */
export const MOCK_API_KEY = 'mock-boxo-api-key';

export interface MockFault {
  /** Substring of the request path this fault applies to, e.g. "service-available". */
  match: string;
  status: number;
  retryAfter?: number;
  /** Response body; omit to get the body shape a real failure of that class has. */
  body?: unknown;
  /** How many matching requests this fault swallows. Defaults to 1. */
  times?: number;
}

export interface MockRequest {
  method: string;
  path: string;
  query: Record<string, string>;
  body: string | null;
  authorized: boolean;
  at: string;
}

/**
 * Drives the stand-in BOXO API that runs inside the Magento container.
 *
 * The module talks to it over localhost, so every call here goes through `docker exec` rather than
 * over the network: the mock is deliberately not reachable from outside the container.
 */
export default class BoxoMock {
  private readonly configuredContainer?: string;
  private resolvedDir?: string;

  constructor(container = process.env.MAGENTO_CONTAINER) {
    // Resolved lazily: specs construct this at module scope, and throwing there breaks test
    // collection for the whole suite rather than failing the specs that actually need it.
    this.configuredContainer = container;
  }

  private get container(): string {
    if (!this.configuredContainer) {
      throw new Error('MAGENTO_CONTAINER env var is required to drive the BOXO mock server.');
    }

    return this.configuredContainer;
  }

  /**
   * Where the mock server lives inside the container. Probed rather than hardcoded: a local
   * checkout mounts the module somewhere different than the VPS runner copies it to.
   */
  private get serverDir(): string {
    if (!this.resolvedDir) {
      this.resolvedDir = `${moduleDir(this.container, 'magento2-boxo')}/Test/End-2-end/mock-server`;
    }

    return this.resolvedDir;
  }

  /**
   * Start the mock server if it is not already listening, and point the module at it.
   * Safe to call repeatedly.
   */
  ensureRunning(): void {
    if (!this.isListening()) {
      this.exec(
        `nohup php -S 127.0.0.1:${MOCK_PORT} ${this.serverDir}/server.php ` +
        `> /tmp/boxo-mock.log 2>&1 & disown`
      );

      for (let attempt = 0; attempt < 25; attempt++) {
        if (this.isListening()) break;
        execSync('sleep 0.2');
      }

      if (!this.isListening()) {
        throw new Error(`BOXO mock server did not come up on port ${MOCK_PORT}. See /tmp/boxo-mock.log.`);
      }
    }

    this.exec(`bin/magento config:set ${CONFIG_PATH_BASE_URL} ${MOCK_BASE_URL}`);
    this.exec(`bin/magento config:set ${CONFIG_PATH_API_KEY} ${MOCK_API_KEY}`);
    this.exec('bin/magento cache:flush config');

    this.assertModuleIsPointedAtTheMock();
  }

  /**
   * Confirm the module really resolves to the mock.
   *
   * Without this the suite happily runs against api.boxo.nu when the override does not take —
   * every assertion then fails for the wrong reason, and the run is measuring BOXO's uptime.
   */
  private assertModuleIsPointedAtTheMock(): void {
    const configured = this.exec(
      `bin/magento config:show ${CONFIG_PATH_BASE_URL} 2>&1 || true`
    ).trim();

    if (!configured.includes(MOCK_BASE_URL)) {
      throw new Error(
        `The module is not pointed at the mock server. ${CONFIG_PATH_BASE_URL} reads: ${configured}\n` +
        'Refusing to run: the suite would talk to the real BOXO API.'
      );
    }
  }

  /** Remove the override so the module points at api.boxo.nu again. */
  disable(): void {
    this.exec(`bin/magento config:set ${CONFIG_PATH_BASE_URL} '' || true`);
    this.exec('bin/magento cache:flush config');
  }

  /** Wipe seeded postcodes, faults and the recorded request log. */
  reset(): void {
    this.control('POST', 'reset');
  }

  /**
   * Declare which postcodes BOXO reports as serviceable.
   *
   * Only the exceptions need seeding: an unseeded postcode comes back available, so a spec that
   * forgot to seed fails on its own assertion rather than on a surprise "not available".
   */
  seedAvailability(available: Record<string, boolean>): void {
    this.control('POST', 'seed', { available });
  }

  /** Queue fault injections that fire on the next matching request(s). */
  injectFaults(faults: MockFault[]): void {
    this.control('POST', 'faults', { faults });
  }

  /** Every request the module made since the last reset, in order. */
  requests(): MockRequest[] {
    return this.control('GET', 'requests').requests ?? [];
  }

  /** Requests whose path contains the given fragment. */
  requestsMatching(fragment: string): MockRequest[] {
    return this.requests().filter((request) => request.path.includes(fragment));
  }

  private isListening(): boolean {
    try {
      const status = this.exec(
        `curl -s -o /dev/null -w '%{http_code}' ${MOCK_BASE_URL}/__control/state || true`
      );
      return status.trim() === '200';
    } catch {
      return false;
    }
  }

  private control(method: 'GET' | 'POST', action: string, payload?: unknown): any {
    const url = `${MOCK_BASE_URL}/__control/${action}`;
    const command = method === 'POST'
      ? `curl -s -X POST ${url} -d @-`
      : `curl -s ${url}`;

    const output = method === 'POST'
      ? this.execWithInput(command, JSON.stringify(payload ?? {}))
      : this.exec(command);

    try {
      return JSON.parse(output);
    } catch {
      throw new Error(`BOXO mock control call ${method} ${action} returned non-JSON: ${output}`);
    }
  }

  private exec(command: string): string {
    return execSync(`docker exec ${this.container} bash -lc ${JSON.stringify(command)}`, {
      encoding: 'utf-8',
      timeout: 60000,
    });
  }

  private execWithInput(command: string, input: string): string {
    return execSync(`docker exec -i ${this.container} bash -lc ${JSON.stringify(command)}`, {
      encoding: 'utf-8',
      input,
      timeout: 60000,
    });
  }
}
