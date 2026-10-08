<?php
/**
 * Copyright Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 *
 * Stand-in for the BOXO API, used by the E2E suite.
 *
 * Runs as a PHP built-in server inside the Magento container:
 *
 *     php -S 127.0.0.1:18092 server.php
 *
 * and the module is pointed at it with:
 *
 *     bin/magento config:set magmodules_boxo/developer/api_base_url http://127.0.0.1:18092
 *
 * Why a stand-in at all: the failures that actually hurt a merchant — a 500 while the customer is
 * choosing packaging, a rejected key, a body that is not the JSON we assumed — cannot be produced
 * against the real BOXO API on demand. Untested error paths are where the bugs live; the happy
 * path gets exercised by every merchant every day.
 *
 * Everything the tests need to steer it lives under /__control:
 *
 *   POST /__control/reset               wipe all state
 *   POST /__control/seed                {"available": {"1012AB": true, "9999ZZ": false}}
 *   POST /__control/faults              queue fault injections (see applyFault)
 *   GET  /__control/requests            every request the module made, in order
 *   GET  /__control/state               full state dump
 *
 * State lives in a JSON file so it survives across the many short-lived PHP processes the
 * built-in server spawns, and so a test can inspect it without going through HTTP.
 */
declare(strict_types=1);

// This file is not Magento code: it is a standalone HTTP responder run by the PHP built-in server.
// Writing a response body and ending the request is the whole job here.
// phpcs:disable Magento2.Security.LanguageConstruct.DirectOutput
// phpcs:disable Magento2.Security.LanguageConstruct.ExitUsage

const STATE_FILE = '/tmp/boxo-mock-state.json';

/**
 * The key the suite configures. BOXO authenticates with a flat API key header rather than OAuth,
 * so there is no token endpoint to stand in for.
 */
const API_KEY = 'mock-boxo-api-key';

$method = $_SERVER['REQUEST_METHOD'];
$path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '', '/');
$segments = $path === '' ? [] : explode('/', $path);
$query = [];
parse_str((string)parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY), $query);
$rawBody = file_get_contents('php://input') ?: '';

$state = loadState();

if (($segments[0] ?? '') === '__control') {
    handleControl($state, $method, array_slice($segments, 1), $rawBody);
    exit;
}

recordRequest($state, $method, $path, $query, $rawBody);

if ($fault = takeFault($state, $path)) {
    saveState($state);
    applyFault($fault);
    exit;
}

if (!isAuthenticated()) {
    saveState($state);
    problem(401, 'Unauthorized', 'The API key is missing or not accepted.');
}

// ---------------------------------------------------------------------------------------------
// GET /checkout/service-available/{postcode}
//
// The only endpoint the module calls. Shaped like the real answer: a flat object with an
// "available" boolean. A malformed postcode is the platform's own 400, not ours.
// ---------------------------------------------------------------------------------------------
if (($segments[0] ?? '') === 'checkout' && ($segments[1] ?? '') === 'service-available') {
    $postcode = strtoupper(str_replace(' ', '', (string)($segments[2] ?? '')));

    if (!preg_match('/^[1-9][0-9]{3}[A-Z]{2}$/', $postcode)) {
        saveState($state);
        problem(400, 'Bad Request', sprintf('"%s" is not a Dutch postcode.', $postcode));
    }

    saveState($state);

    // Unseeded postcodes are available: the suite seeds the exceptions it cares about, so a spec
    // that forgot to seed fails on its assertion rather than on a surprise "not available".
    respond(200, ['available' => (bool)($state['available'][$postcode] ?? true)]);
}

saveState($state);
problem(404, 'Not Found', sprintf('No handler for /%s', $path));

// ---------------------------------------------------------------------------------------------
// Control plane
// ---------------------------------------------------------------------------------------------

function handleControl(array &$state, string $method, array $segments, string $rawBody): void
{
    $action = $segments[0] ?? '';
    $payload = json_decode($rawBody, true) ?: [];

    switch ($action) {
        case 'reset':
            saveState(emptyState());
            respond(200, ['reset' => true]);
            // no break — respond() exits
        case 'seed':
            foreach ($payload as $key => $value) {
                $state[$key] = $value;
            }
            saveState($state);
            respond(200, ['seeded' => array_keys($payload)]);
            // no break
        case 'faults':
            foreach ($payload['faults'] ?? [] as $fault) {
                $state['faults'][] = [
                    'match' => (string)($fault['match'] ?? ''),
                    'status' => (int)($fault['status'] ?? 500),
                    'retryAfter' => (int)($fault['retryAfter'] ?? 0),
                    'body' => $fault['body'] ?? null,
                    'times' => (int)($fault['times'] ?? 1),
                ];
            }
            saveState($state);
            respond(200, ['faults' => count($state['faults'])]);
            // no break
        case 'requests':
            respond(200, ['requests' => $state['requests']]);
            // no break
        case 'state':
            respond(200, $state);
            // no break
        default:
            problem(404, 'Not Found', sprintf('Unknown control action "%s"', $action));
    }
}

// ---------------------------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------------------------

function takeFault(array &$state, string $path): ?array
{
    foreach ($state['faults'] as $index => $fault) {
        if ($fault['match'] !== '' && !str_contains($path, $fault['match'])) {
            continue;
        }

        $state['faults'][$index]['times']--;
        if ($state['faults'][$index]['times'] <= 0) {
            unset($state['faults'][$index]);
            $state['faults'] = array_values($state['faults']);
        }

        return $fault;
    }

    return null;
}

function applyFault(array $fault): void
{
    if ($fault['retryAfter'] > 0) {
        header('Retry-After: ' . $fault['retryAfter']);
    }

    if ($fault['body'] !== null) {
        http_response_code($fault['status']);
        header('Content-Type: application/json');
        echo is_string($fault['body']) ? $fault['body'] : json_encode($fault['body']);
        return;
    }

    // No body configured: mirror what a real outage looks like on the wire.
    //
    // The multi-line body matters more than it looks. An HTTP client builds its exception message
    // out of the status line *and an excerpt of this body*, and a multi-line body is what turns a
    // plain 5xx into a multi-line exception message — the input that broke the error handling in
    // every Magmodules review module. A mock that always answers with tidy single-line JSON never
    // reproduces it.
    if ($fault['status'] >= 500) {
        http_response_code($fault['status']);
        header('Content-Type: text/html');
        echo "<html>\n<body>\n<h1>" . $fault['status'] . "</h1>\n<p>Gateway failure.</p>\n</body>\n</html>";
        return;
    }

    if ($fault['status'] === 429) {
        http_response_code(429);
        echo '';
        return;
    }

    problem($fault['status'], 'Unauthorized', "Injected fault.\nThe key was not accepted.");
}

/**
 * BOXO reads the key from X-Api-Key. Checked case-insensitively through getallheaders(), because
 * the built-in server normalises header names differently than nginx does and a suite that only
 * passes under one of them is testing the server, not the module.
 */
function isAuthenticated(): bool
{
    foreach (getallheaders() as $name => $value) {
        if (strtolower($name) === 'x-api-key') {
            return $value === API_KEY;
        }
    }

    return false;
}

function recordRequest(array &$state, string $method, string $path, array $query, string $rawBody): void
{
    $state['requests'][] = [
        'method' => $method,
        'path' => $path,
        'query' => $query,
        'body' => $rawBody === '' ? null : substr($rawBody, 0, 4096),
        'authorized' => isAuthenticated(),
        'at' => date('c'),
    ];
}

function emptyState(): array
{
    return ['faults' => [], 'requests' => [], 'available' => []];
}

function loadState(): array
{
    if (!is_file(STATE_FILE)) {
        return emptyState();
    }

    $decoded = json_decode((string)file_get_contents(STATE_FILE), true);

    return is_array($decoded) ? $decoded + emptyState() : emptyState();
}

function saveState(array $state): void
{
    file_put_contents(STATE_FILE, json_encode($state, JSON_PRETTY_PRINT), LOCK_EX);
}

function respond(int $status, array $body): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($body);
    exit;
}

function problem(int $status, string $title, string $detail): void
{
    http_response_code($status);
    header('Content-Type: application/problem+json');
    echo json_encode([
        'title' => $title,
        'status' => $status,
        'detail' => $detail,
        'instance' => $_SERVER['REQUEST_URI'] ?? '',
    ]);
    exit;
}
