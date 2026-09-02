<?php

namespace App\Services;

use App\Models\Connector;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * OdooConnectorService communicates with Odoo (v15 through v19) via JSON-RPC 2.0.
 *
 * Supported features:
 *  - Authentication via /web/session/authenticate
 *  - Model search_read / execute_kw calls via /jsonrpc
 *  - QWeb report PDF rendering & download
 *  - Connection testing and version detection
 */
class OdooConnectorService
{
    /**
     * Authenticate to an Odoo instance via JSON-RPC.
     *
     * @param string $url Base URL of Odoo instance (e.g. http://odoo:8069)
     * @param string $db Database name
     * @param string $login Username or email
     * @param string $password Password or API key
     * @return array{success: bool, uid: ?int, session_id: ?string, server_version: ?string, error: ?string}
     */
    public function authenticate(string $url, string $db, string $login, string $password): array
    {
        $authUrl = rtrim($url, '/') . '/web/session/authenticate';

        try {
            $response = Http::timeout(15)->post($authUrl, [
                'jsonrpc' => '2.0',
                'method'  => 'call',
                'params'  => [
                    'db'       => $db,
                    'login'    => $login,
                    'password' => $password,
                ],
            ]);

            if ($response->failed()) {
                return [
                    'success'        => false,
                    'uid'            => null,
                    'session_id'     => null,
                    'server_version' => null,
                    'error'          => "HTTP {$response->status()}: {$response->body()}",
                ];
            }

            $body = $response->json();

            if (!empty($body['error'])) {
                $errMessage = $body['error']['data']['message'] ?? $body['error']['message'] ?? 'Authentication failed';
                return [
                    'success'        => false,
                    'uid'            => null,
                    'session_id'     => null,
                    'server_version' => null,
                    'error'          => $errMessage,
                ];
            }

            $result = $body['result'] ?? [];
            $uid = $result['uid'] ?? null;

            if (!$uid) {
                return [
                    'success'        => false,
                    'uid'            => null,
                    'session_id'     => null,
                    'server_version' => null,
                    'error'          => 'Authentication returned empty UID. Check credentials.',
                ];
            }

            // Extract session_id from response cookies or result
            $sessionId = null;
            $cookies = $response->cookies();
            $sessionCookie = $cookies->getCookieByName('session_id');
            if ($sessionCookie) {
                $sessionId = $sessionCookie->getValue();
            }

            return [
                'success'        => true,
                'uid'            => (int) $uid,
                'session_id'     => $sessionId,
                'server_version' => $result['server_version'] ?? '19.0',
                'error'          => null,
            ];
        } catch (\Throwable $e) {
            return [
                'success'        => false,
                'uid'            => null,
                'session_id'     => null,
                'server_version' => null,
                'error'          => 'Connection failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Call any Odoo model method using /jsonrpc object execute_kw.
     */
    public function call(
        string $url,
        string $db,
        int $uid,
        string $password,
        string $model,
        string $method,
        array $args = [],
        array $kwargs = []
    ): mixed {
        $endpoint = rtrim($url, '/') . '/jsonrpc';

        $payload = [
            'jsonrpc' => '2.0',
            'method'  => 'call',
            'params'  => [
                'service' => 'object',
                'method'  => 'execute_kw',
                'args'    => [
                    $db,
                    $uid,
                    $password,
                    $model,
                    $method,
                    $args,
                    $kwargs,
                ],
            ],
            'id' => random_int(1000, 99999),
        ];

        $response = Http::timeout(25)->post($endpoint, $payload);

        if ($response->failed()) {
            throw new \RuntimeException("Odoo call failed: HTTP {$response->status()} - {$response->body()}");
        }

        $body = $response->json();

        if (!empty($body['error'])) {
            $msg = $body['error']['data']['message'] ?? $body['error']['message'] ?? json_encode($body['error']);
            throw new \RuntimeException("Odoo error: {$msg}");
        }

        return $body['result'] ?? null;
    }

    /**
     * Search and read records from an Odoo model.
     */
    public function readRecords(
        string $url,
        string $db,
        int $uid,
        string $password,
        string $model,
        array $domain = [],
        array $fields = [],
        int $limit = 80
    ): array {
        $result = $this->call(
            $url,
            $db,
            $uid,
            $password,
            $model,
            'search_read',
            [$domain],
            ['fields' => $fields, 'limit' => $limit]
        );

        return is_array($result) ? $result : [];
    }

    /**
     * Render and download a QWeb PDF report from Odoo.
     *
     * @param string $url Base URL
     * @param string $db Database name
     * @param int $uid User ID
     * @param string $password User password / API key
     * @param string $reportName Odoo report XML ID (e.g. 'stock.report_deliveryslip' or 'account.report_invoice')
     * @param int[] $recordIds IDs of records to render
     * @return string Raw PDF binary string
     */
    public function renderReportPdf(
        string $url,
        string $db,
        int $uid,
        string $password,
        string $reportName,
        array $recordIds
    ): string {
        // Call ir.actions.report _render_qweb_pdf via execute_kw
        $result = $this->call(
            $url,
            $db,
            $uid,
            $password,
            'ir.actions.report',
            '_render_qweb_pdf',
            [$reportName, $recordIds]
        );

        // Odoo returns [binary_string_or_base64, "pdf"]
        if (is_array($result) && !empty($result[0])) {
            $content = $result[0];
            // If base64-encoded string, decode it
            if (is_string($content) && !str_starts_with($content, '%PDF')) {
                $decoded = base64_decode($content, true);
                if ($decoded !== false && str_starts_with($decoded, '%PDF')) {
                    return $decoded;
                }
            }
            return $content;
        }

        throw new \RuntimeException("Failed to render Odoo report '{$reportName}'.");
    }

    /**
     * Test connection for an Odoo connector configuration.
     *
     * @param array $config Connector config array {url, db, username, password}
     * @return array{success: bool, message: string, latency_ms: ?int}
     */
    public function testConnection(array $config): array
    {
        $url      = $config['url'] ?? $config['endpoint_url'] ?? null;
        $db       = $config['db'] ?? $config['database'] ?? null;
        $login    = $config['login'] ?? $config['username'] ?? null;
        $password = $config['password'] ?? $config['api_key'] ?? null;

        if (empty($url) || empty($db) || empty($login) || empty($password)) {
            return [
                'success'    => false,
                'message'    => 'Missing required Odoo configuration fields: url, db, login/username, password/api_key.',
                'latency_ms' => null,
            ];
        }

        $start = microtime(true);
        $authResult = $this->authenticate($url, $db, $login, $password);
        $latency = (int) ((microtime(true) - $start) * 1000);

        if (!$authResult['success']) {
            return [
                'success'    => false,
                'message'    => 'Odoo Auth Failed: ' . ($authResult['error'] ?? 'Unknown error'),
                'latency_ms' => $latency,
            ];
        }

        $version = $authResult['server_version'] ?? '19.0';
        return [
            'success'    => true,
            'message'    => "Connected to Odoo {$version} (UID: {$authResult['uid']}).",
            'latency_ms' => $latency,
        ];
    }
}
