<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class PrintHubException extends RuntimeException {}
class PrintHubConnectionException extends PrintHubException {}
class PrintHubValidationException extends PrintHubException {
    public array $errors;
    public function __construct(string $message, array $errors = []) {
        parent::__construct($message);
        $this->errors = $errors;
    }
}

/**
 * PrintHubClient — PHP SDK for Print Hub v2 (Multi-Branch Edition)
 *
 * Supports branch-aware printing, template discovery, schema validation,
 * preview, batch printing, job polling, printer pools, scheduling,
 * document management, approvals, connectors, and formula editor.
 *
 * @version 2.2
 */
class PrintHubClient
{
    private Client $http;
    private string $cacheDir;
    private ?string $defaultBranchCode = null;
    private LoggerInterface $logger;
    private int $cacheTtl;
    private int $maxRetries;
    private int $retryDelayMs;

    /**
     * Create a new PrintHubClient instance.
     *
     * @param string           $baseUrl      The Print Hub server URL (e.g. https://print-hub.example.com)
     * @param string           $apiKey       Your client app API key (from Print Hub > Client Apps)
     * @param int              $timeout      Request timeout in seconds
     * @param string           $cacheDir     Directory for caching schema data
     * @param int              $cacheTtl     Schema cache TTL in seconds (default 600)
     * @param int              $maxRetries   Max request retries on transient failures (default 2)
     * @param int              $retryDelayMs Initial retry delay in ms, doubled each attempt (default 200)
     * @param LoggerInterface  $logger       PSR-3 logger for debugging (default NullLogger)
     */
    public function __construct(
        string $baseUrl,
        string $apiKey,
        int $timeout = 15,
        string $cacheDir = '/tmp',
        int $cacheTtl = 600,
        int $maxRetries = 2,
        int $retryDelayMs = 200,
        ?LoggerInterface $logger = null
    ) {
        $this->http = new Client([
            'base_uri' => rtrim($baseUrl, '/') . '/',
            'timeout'  => $timeout,
            'headers'  => [
                'X-API-Key' => $apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ]
        ]);
        $this->cacheDir = rtrim($cacheDir, '/');
        $this->cacheTtl = $cacheTtl;
        $this->maxRetries = $maxRetries;
        $this->retryDelayMs = $retryDelayMs;
        $this->logger = $logger ?? new NullLogger();
    }

    // =========================================================================
    // Branch Configuration
    // =========================================================================

    /**
     * Set the default branch for all subsequent print/query calls.
     *
     * This avoids passing branchCode to every method. Can be overridden per-call.
     *
     * @param string $branchCode  e.g. "SDP-SBY"
     * @return $this
     */
    public function setBranch(string $branchCode): self
    {
        $this->defaultBranchCode = $branchCode;
        return $this;
    }

    /**
     * Get the currently configured default branch code.
     */
    public function getBranchCode(): ?string
    {
        return $this->defaultBranchCode;
    }

    /**
     * Clear all cached schemas, or a specific template's schema.
     */
    public function clearCache(?string $templateName = null): void
    {
        if ($templateName) {
            $file = $this->cacheFile($templateName);
            if (file_exists($file)) {
                unlink($file);
                $this->logger->debug("PrintHubClient: Cache cleared for template '{$templateName}'");
            }
        } else {
            $files = glob($this->cacheDir . '/printhub_schema_*.json');
            foreach ($files as $file) {
                unlink($file);
            }
            $this->logger->debug('PrintHubClient: All schema cache cleared');
        }
    }

    // =========================================================================
    // Discovery
    // =========================================================================

    /**
     * List all available branches.
     *
     * @return array  [['id' => 1, 'code' => 'SDP-SBY', 'name' => '...', 'company' => '...'], ...]
     */
    public function getBranches(): array
    {
        return $this->get('api/v1/branches')['branches'] ?? [];
    }

    /**
     * List online agents, optionally filtered by branch.
     *
     * @param string|null $branchCode  Filter by branch code (null = all)
     */
    public function getOnlineAgents(?string $branchCode = null): array
    {
        $params = [];
        $bc = $branchCode ?? $this->defaultBranchCode;
        if ($bc) $params['branch_code'] = $bc;

        $query = $params ? '?' . http_build_query($params) : '';
        return $this->get("api/v1/agents/online{$query}")['agents'] ?? [];
    }

    /**
     * List all available queues (print profiles).
     *
     * @param string|null $branchCode  Filter queues by branch
     * @param bool        $detailed     Return full queue configuration (margins, tray, color, etc.)
     */
    public function getQueues(?string $branchCode = null, bool $detailed = false): array
    {
        $params = [];
        $bc = $branchCode ?? $this->defaultBranchCode;
        if ($bc) $params['branch_code'] = $bc;
        if ($detailed) $params['detailed'] = '1';

        $query = $params ? '?' . http_build_query($params) : '';
        return $this->get("api/v1/queues{$query}")['queues'] ?? [];
    }

    /**
     * List all available templates.
     */
    public function getTemplates(): array
    {
        return $this->get('api/v1/templates')['templates'] ?? [];
    }

    /**
     * Get detailed info for a specific template.
     */
    public function getTemplate(string $name): array
    {
        return $this->get("api/v1/templates/{$name}");
    }

    /**
     * Get the required data schema for a template (cached for 10 minutes).
     *
     * @param string $name      Template name
     * @param bool   $useCache  Use local file cache
     */
    public function getTemplateSchema(string $name, bool $useCache = true): array
    {
        $cacheFile = $this->cacheFile($name);
        if ($useCache && file_exists($cacheFile) && filemtime($cacheFile) > (time() - $this->cacheTtl)) {
            $this->logger->debug("PrintHubClient: Schema cache HIT for template '{$name}'");
            $data = json_decode(file_get_contents($cacheFile), true);
            if (is_array($data)) return $data;
        }

        $this->logger->debug("PrintHubClient: Schema cache MISS for template '{$name}', fetching from server");
        $schema = $this->get("api/v1/templates/{$name}/schema");

        // Atomic write: write to temp file then rename
        $tmpFile = $cacheFile . '.' . uniqid('tmp', true);
        if (@file_put_contents($tmpFile, json_encode($schema, JSON_UNESCAPED_SLASHES)) !== false) {
            @rename($tmpFile, $cacheFile);
        }

        return $schema;
    }

    private function cacheFile(string $name): string
    {
        $key = hash('sha256', $this->getBaseUrl() . '::' . $name);
        return $this->cacheDir . '/printhub_schema_' . $key . '.json';
    }

    private function getBaseUrl(): string
    {
        // Extract base URL from Guzzle config for cache scoping
        $config = $this->http->getConfig();
        $baseUri = $config['base_uri'] ?? '';
        return rtrim((string) $baseUri, '/');
    }

    // =========================================================================
    // Schema Management
    // =========================================================================

    /**
     * Register or update a data schema for template binding.
     */
    public function registerSchema(string $schemaName, array $schemaData): array
    {
        $payload = array_merge(['schema_name' => $schemaName], $schemaData);
        return $this->post('api/v1/schema', $payload);
    }

    /**
     * Validate data against a template's schema (client-side).
     * Returns an array of error messages. Empty = valid.
     */
    public function validateData(string $templateName, array $data): array
    {
        $errors = [];
        $schema = $this->getTemplateSchema($templateName);

        foreach ($schema['required_fields'] ?? [] as $key => $meta) {
            $required = $meta['required'] ?? false;
            $value = $this->resolveValue($key, $data);

            if ($required && ($value === null || $value === '')) {
                $label = $meta['label'] ?? $key;
                $errors[] = "Missing required field: {$label} ({$key})";
            }

            if ($value !== null && $value !== '') {
                $type = $meta['type'] ?? 'string';
                if ($type === 'number' && !is_numeric($value)) {
                    $errors[] = "Field '{$key}' expected numeric, got: " . gettype($value);
                }
            }
        }

        foreach ($schema['required_tables'] ?? [] as $tableKey => $tableMeta) {
            $rows = $this->resolveValue($tableKey, $data);
            if ($rows !== null && !is_array($rows)) {
                $errors[] = "Table '{$tableKey}' expected array of rows.";
                continue;
            }

            $minRows = $tableMeta['min_rows'] ?? null;
            if ($minRows && is_array($rows) && count($rows) < $minRows) {
                $errors[] = "Table '{$tableKey}' requires at least {$minRows} row(s), got " . count($rows) . ".";
            }
        }

        return $errors;
    }

    // =========================================================================
    // Printing
    // =========================================================================

    /**
     * Print using a named template (synchronous).
     *
     * The system uses branch_code to route the job to the correct agent/printer
     * via the branch's configured template defaults.
     *
     * @param string      $template        Template name (e.g. "invoice_sewa")
     * @param array       $data            Data to fill into the template
     * @param string      $referenceId     Your reference ID for tracking
     * @param string      $queue           Queue/profile name override (optional)
     * @param string|null $branchCode      Branch code override (or uses default)
     * @param array       $options         Additional options (skip_validation, copies, etc.)
     * @param array       $parameters      Runtime parameter values keyed by parameter name
     * @param int|null    $poolId          Printer pool ID for automatic printer selection
     * @param int|null    $documentId      Attach an existing uploaded document
     * @param string|null $webhookUrl      URL to receive job status callbacks
     * @param int|null    $agentId         Specific agent ID to route the job to
     * @param string|null $printer         Specific printer name override
     * @param int|null    $branchId        Branch ID override (alternative to branch_code)
     * @param int|null    $priority        Job priority (higher = more urgent)
     * @param string|null $scheduledAt     ISO 8601 datetime for scheduled printing
     * @param string|null $recurrence      Recurrence pattern: daily, weekly, monthly, none
     * @param string|null $recurrenceEndAt ISO 8601 datetime to stop recurring
     * @param int|null    $recurrenceCount Max number of recurring executions
     *
     * @return array  { status, job_id, agent, printer, template, queue }
     * @throws PrintHubValidationException  if schema validation fails
     */
    public function printWithTemplate(
        string $template,
        array  $data,
        string $referenceId = '',
        string $queue = '',
        ?string $branchCode = null,
        array  $options = [],
        array  $parameters = [],
        ?int   $poolId = null,
        ?int   $documentId = null,
        ?string $webhookUrl = null,
        ?int   $agentId = null,
        ?string $printer = null,
        ?int   $branchId = null,
        ?int   $priority = null,
        ?string $scheduledAt = null,
        ?string $recurrence = null,
        ?string $recurrenceEndAt = null,
        ?int   $recurrenceCount = null
    ): array {
        $validation = $this->validateData($template, $data);
        if (!empty($validation) && empty($options['skip_validation'])) {
            throw new PrintHubValidationException("Schema validation failed", $validation);
        }

        $bc = $branchCode ?? $this->defaultBranchCode;

        $payload = array_merge([
            'template'     => $template,
            'data'         => $data,
            'reference_id' => $referenceId ?: null,
            'queue'        => $queue ?: null,
            'branch_code'  => $bc,
        ], $options);

        if (!empty($parameters)) {
            $payload['parameters'] = $parameters;
        }
        if ($poolId !== null) $payload['pool_id'] = $poolId;
        if ($documentId !== null) $payload['document_id'] = $documentId;
        if ($webhookUrl !== null) $payload['webhook_url'] = $webhookUrl;
        if ($agentId !== null) $payload['agent_id'] = $agentId;
        if ($printer !== null) $payload['printer'] = $printer;
        if ($branchId !== null) $payload['branch_id'] = $branchId;
        if ($priority !== null) $payload['priority'] = $priority;
        if ($scheduledAt !== null) $payload['scheduled_at'] = $scheduledAt;
        if ($recurrence !== null) $payload['recurrence'] = $recurrence;
        if ($recurrenceEndAt !== null) $payload['recurrence_end_at'] = $recurrenceEndAt;
        if ($recurrenceCount !== null) $payload['recurrence_count'] = $recurrenceCount;

        return $this->post('api/v1/print', $payload);
    }

    /**
     * Print using a named template (asynchronous / non-blocking).
     *
     * Returns a Guzzle Promise. Resolve with ->wait() or use ->then().
     *
     * @param string      $template        Template name
     * @param array       $data            Template data
     * @param string      $referenceId     Your reference ID
     * @param string      $queue           Queue/profile name override
     * @param string|null $branchCode      Branch code override
     * @param array       $options         Additional options
     * @param array       $parameters      Runtime parameter values
     * @param int|null    $poolId          Printer pool ID
     * @param int|null    $documentId      Attach an existing uploaded document
     * @param string|null $webhookUrl      Job status callback URL
     * @param int|null    $agentId         Specific agent ID
     * @param string|null $printer         Specific printer name
     * @param int|null    $branchId        Branch ID override
     * @param int|null    $priority        Job priority
     * @param string|null $scheduledAt     ISO 8601 scheduled datetime
     * @param string|null $recurrence      Recurrence pattern
     * @param string|null $recurrenceEndAt Recurrence end datetime
     * @param int|null    $recurrenceCount Max recurring executions
     */
    public function printAsync(
        string $template,
        array  $data,
        string $referenceId = '',
        string $queue = '',
        ?string $branchCode = null,
        array  $options = [],
        array  $parameters = [],
        ?int   $poolId = null,
        ?int   $documentId = null,
        ?string $webhookUrl = null,
        ?int   $agentId = null,
        ?string $printer = null,
        ?int   $branchId = null,
        ?int   $priority = null,
        ?string $scheduledAt = null,
        ?string $recurrence = null,
        ?string $recurrenceEndAt = null,
        ?int   $recurrenceCount = null
    ): PromiseInterface {
        $validation = $this->validateData($template, $data);
        if (!empty($validation) && empty($options['skip_validation'])) {
            throw new PrintHubValidationException("Schema validation failed", $validation);
        }

        $bc = $branchCode ?? $this->defaultBranchCode;

        $payload = array_merge([
            'template'     => $template,
            'data'         => $data,
            'reference_id' => $referenceId ?: null,
            'queue'        => $queue ?: null,
            'branch_code'  => $bc,
        ], $options);

        if (!empty($parameters)) {
            $payload['parameters'] = $parameters;
        }
        if ($poolId !== null) $payload['pool_id'] = $poolId;
        if ($documentId !== null) $payload['document_id'] = $documentId;
        if ($webhookUrl !== null) $payload['webhook_url'] = $webhookUrl;
        if ($agentId !== null) $payload['agent_id'] = $agentId;
        if ($printer !== null) $payload['printer'] = $printer;
        if ($branchId !== null) $payload['branch_id'] = $branchId;
        if ($priority !== null) $payload['priority'] = $priority;
        if ($scheduledAt !== null) $payload['scheduled_at'] = $scheduledAt;
        if ($recurrence !== null) $payload['recurrence'] = $recurrence;
        if ($recurrenceEndAt !== null) $payload['recurrence_end_at'] = $recurrenceEndAt;
        if ($recurrenceCount !== null) $payload['recurrence_count'] = $recurrenceCount;

        return $this->http->postAsync('api/v1/print', ['json' => $payload])->then(
            function ($response) {
                return json_decode($response->getBody()->getContents(), true);
            },
            function ($exception) {
                throw new PrintHubConnectionException("Async Print failed: " . $exception->getMessage());
            }
        );
    }

    /**
     * Print a raw PDF file (base64 encoded).
     *
     * @param string      $base64Pdf       Base64-encoded PDF content
     * @param string      $referenceId     Your reference ID for tracking
     * @param string      $queue           Queue/profile name override
     * @param string|null $branchCode      Branch code override (or uses default)
     * @param array       $options         Additional options
     * @param int|null    $poolId          Printer pool ID for automatic printer selection
     * @param int|null    $documentId      Attach an existing uploaded document
     * @param string|null $webhookUrl      URL to receive job status callbacks
     * @param int|null    $agentId         Specific agent ID to route the job to
     * @param string|null $printer         Specific printer name override
     * @param int|null    $branchId        Branch ID override (alternative to branch_code)
     * @param int|null    $priority        Job priority (higher = more urgent)
     * @param string|null $scheduledAt     ISO 8601 datetime for scheduled printing
     * @param string|null $recurrence      Recurrence pattern: daily, weekly, monthly, none
     * @param string|null $recurrenceEndAt ISO 8601 datetime to stop recurring
     * @param int|null    $recurrenceCount Max number of recurring executions
     */
    public function printRawPdf(
        string $base64Pdf,
        string $referenceId = '',
        string $queue = '',
        ?string $branchCode = null,
        array  $options = [],
        ?int   $poolId = null,
        ?int   $documentId = null,
        ?string $webhookUrl = null,
        ?int   $agentId = null,
        ?string $printer = null,
        ?int   $branchId = null,
        ?int   $priority = null,
        ?string $scheduledAt = null,
        ?string $recurrence = null,
        ?string $recurrenceEndAt = null,
        ?int   $recurrenceCount = null
    ): array {
        $base64Pdf = preg_replace('/\s+/', '', $base64Pdf);
        $padding = strlen($base64Pdf) % 4;
        if ($padding > 0 && $padding !== 1) {
            $base64Pdf = str_pad($base64Pdf, strlen($base64Pdf) + (4 - $padding), '=', STR_PAD_RIGHT);
        }
        if (strlen($base64Pdf) % 4 === 1) {
            throw new PrintHubException("Invalid base64 string length for PDF document.");
        }

        $bc = $branchCode ?? $this->defaultBranchCode;

        $payload = array_merge([
            'document_base64' => $base64Pdf,
            'reference_id'    => $referenceId ?: null,
            'queue'           => $queue ?: null,
            'branch_code'     => $bc,
        ], $options);

        if ($poolId !== null) $payload['pool_id'] = $poolId;
        if ($documentId !== null) $payload['document_id'] = $documentId;
        if ($webhookUrl !== null) $payload['webhook_url'] = $webhookUrl;
        if ($agentId !== null) $payload['agent_id'] = $agentId;
        if ($printer !== null) $payload['printer'] = $printer;
        if ($branchId !== null) $payload['branch_id'] = $branchId;
        if ($priority !== null) $payload['priority'] = $priority;
        if ($scheduledAt !== null) $payload['scheduled_at'] = $scheduledAt;
        if ($recurrence !== null) $payload['recurrence'] = $recurrence;
        if ($recurrenceEndAt !== null) $payload['recurrence_end_at'] = $recurrenceEndAt;
        if ($recurrenceCount !== null) $payload['recurrence_count'] = $recurrenceCount;

        return $this->post('api/v1/print', $payload);
    }

    /**
     * Print multiple jobs in a single request.
     *
     * Each job in the array can have: template, data, document_base64,
     * printer, queue, branch_code, reference_id.
     *
     * @param array $jobs  Array of job payloads
     */
    public function printBatch(array $jobs): array
    {
        // Auto-fill branch_code for jobs that don't specify one
        if ($this->defaultBranchCode) {
            foreach ($jobs as &$job) {
                if (empty($job['branch_code'])) {
                    $job['branch_code'] = $this->defaultBranchCode;
                }
            }
            unset($job);
        }

        return $this->post('api/v1/print/batch', ['jobs' => $jobs]);
    }

    /**
     * Generate a PDF preview without queuing a print job.
     *
     * @param string $template   Template name
     * @param array  $data       Template data
     * @param array  $options    Options (paper_size, orientation, etc.)
     * @param array  $parameters Runtime parameter values keyed by parameter name
     * @return string  Raw PDF binary content
     */
    public function preview(string $template, array $data, array $options = [], array $parameters = []): string
    {
        $payload = [
            'template' => $template,
            'data'     => $data,
            'options'  => $options,
        ];

        if (!empty($parameters)) {
            $payload['parameters'] = $parameters;
        }

        try {
            $response = $this->http->post('api/v1/preview', ['json' => $payload]);
            return $response->getBody()->getContents();
        } catch (RequestException $e) {
            $res = $e->getResponse();
            if ($res) {
                $decoded = json_decode($res->getBody()->getContents(), true);
                $message = $decoded['error'] ?? "HTTP " . $res->getStatusCode();
                throw new PrintHubException("Preview failed: {$message}");
            }
            throw new PrintHubConnectionException("Preview connection error: " . $e->getMessage());
        }
    }

    // =========================================================================
    // Job Management
    // =========================================================================

    /**
     * Check the status of a print job.
     *
     * @return array  { job_id, status, reference_id, printer, error, created_at, completed_at }
     */
    public function jobStatus(string $jobId): array
    {
        return $this->get("api/v1/jobs/{$jobId}");
    }

    /**
     * Wait for a job to complete by polling.
     *
     * @param string $jobId           Job UUID
     * @param int    $timeoutSeconds  Maximum time to wait
     * @param int    $pollIntervalMs  Polling interval in milliseconds
     * @return array  Final job status
     * @throws PrintHubException  if timeout is reached
     */
    public function waitForJob(string $jobId, int $timeoutSeconds = 30, int $pollIntervalMs = 500): array
    {
        $start = time();

        while (true) {
            $status = $this->jobStatus($jobId);

            if (in_array($status['status'] ?? '', ['success', 'failed'])) {
                return $status;
            }

            if (time() - $start >= $timeoutSeconds) {
                throw new PrintHubException("Timeout waiting for job {$jobId} after {$timeoutSeconds}s. Last status: " . ($status['status'] ?? 'unknown'));
            }

            usleep($pollIntervalMs * 1000);
        }
    }

    // =========================================================================
    // Connector Registry
    // =========================================================================

    /**
     * Register a new data-source connector.
     *
     * @param string      $name   Human-readable name (e.g. "SDP Finance ERP")
     * @param string      $type   One of: api, webhook, odoo, custom
     * @param array       $config Configuration: endpoint URL, auth type, headers, etc.
     * @param string|null $icon   Optional emoji or icon URL
     * @return array
     */
    public function registerConnector(string $name, string $type, array $config, ?string $icon = null): array
    {
        return $this->post('api/v1/connectors', [
            'name'   => $name,
            'type'   => $type,
            'config' => $config,
            'icon'   => $icon,
        ]);
    }

    /**
     * List all connectors registered for this client app.
     *
     * @return array  ['connectors' => [...]]
     */
    public function listConnectors(): array
    {
        return $this->get('api/v1/connectors');
    }

    /**
     * Update an existing connector.
     *
     * @param string $id     Connector UUID
     * @param array  $data   Fields to update (name, type, config, icon, is_active)
     * @return array
     */
    public function updateConnector(string $id, array $data): array
    {
        return $this->put("api/v1/connectors/{$id}", $data);
    }

    /**
     * Test a connector by sending a HEAD request to its configured URL.
     *
     * @param string $id  Connector UUID
     * @return array  { connector_id, success, message, latency_ms, last_test_at }
     */
    public function testConnector(string $id): array
    {
        return $this->post("api/v1/connectors/{$id}/test", []);
    }

    /**
     * Delete a connector.
     *
     * @param string $id  Connector UUID
     * @return array  { message }
     */
    public function deleteConnector(string $id): array
    {
        return $this->delete("api/v1/connectors/{$id}");
    }

    // =========================================================================
    // Preview Request Handlers (static — for client app integration)
    // =========================================================================

    /**
     * Register a callable that will be invoked when Print Hub requests
     * live preview data via the webhook endpoint (/print-hub-preview).
     *
     * Usage in your client app's /print-hub-preview route handler:
     *
     *   PrintHubClient::handlePreviewRequest(function (array $payload): array {
     *       // Extract schema name or other context from the payload
     *       $schemaName = $payload['connector']['name'] ?? 'default';
     *       $data = fetchYourLiveData($schemaName); // your business logic
     *       return ['data' => $data];
     *   });
     *
     * @param callable $handler  fn(array $payload): array
     */
    public static function handlePreviewRequest(callable $handler): void
    {
        $GLOBALS['_print_hub_preview_handler'] = $handler;
    }

    /**
     * Handle an incoming preview request from Print Hub.
     * Call this from your /print-hub-preview endpoint.
     *
     * Reads the JSON payload from php://input, invokes the registered handler,
     * and sends a JSON response back.
     */
    public static function handleIncomingPreviewRequest(): void
    {
        $payload = json_decode(file_get_contents('php://input'), true) ?? [];
        $handler = $GLOBALS['_print_hub_preview_handler'] ?? null;

        if (! $handler) {
            http_response_code(500);
            echo json_encode([
                'error' => 'No preview handler registered. Call PrintHubClient::handlePreviewRequest() first.',
            ]);
            exit;
        }

        try {
            $result = call_user_func($handler, $payload);
            echo json_encode([
                'data'        => $result['data'] ?? [],
                'received_at' => date('c'),
            ]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'error' => 'Preview handler error: ' . $e->getMessage(),
            ]);
        }
        exit;
    }

    // =========================================================================
    // Connection Test
    // =========================================================================

    /**
     * Test the connection to Print Hub.
     *
     * @return array  { success, message, app_name, agents, server_time }
     */
    public function testConnection(): array
    {
        return $this->get('api/v1/test');
    }

    // =========================================================================
    // Internal Helpers
    // =========================================================================

    private function get(string $path): array
    {
        return $this->request('GET', $path);
    }

    private function post(string $path, array $body): array
    {
        return $this->request('POST', $path, ['json' => $body]);
    }

    private function put(string $path, array $body): array
    {
        return $this->request('PUT', $path, ['json' => $body]);
    }

    private function delete(string $path): array
    {
        return $this->request('DELETE', $path);
    }

    private function request(string $method, string $path, array $options = []): array
    {
        $attempts = 0;
        $lastException = null;

        while ($attempts <= $this->maxRetries) {
            try {
                $response = $this->http->request($method, $path, $options);
                $body = $response->getBody()->getContents();
                $data = json_decode($body, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new PrintHubException("PrintHubClient: Invalid JSON response: " . json_last_error_msg());
                }

                $this->logger->debug("PrintHubClient: {$method} {$path} → {$response->getStatusCode()}");
                return $data ?? [];
            } catch (ConnectException $e) {
                $lastException = $e;
                $this->logger->warning("PrintHubClient: Connection failed (attempt " . ($attempts + 1) . "): " . $e->getMessage());
                $attempts++;
                if ($attempts > $this->maxRetries) {
                    throw new PrintHubConnectionException("PrintHubClient connection error after {$attempts} attempts: " . $e->getMessage(), 0, $e);
                }
                usleep($this->retryDelayMs * 1000 * pow(2, $attempts - 1));
            } catch (RequestException $e) {
                $res = $e->getResponse();
                $statusCode = $res ? $res->getStatusCode() : 0;
                $this->logger->error("PrintHubClient: HTTP {$statusCode} on {$method} {$path}");

                // Retry on server errors (500+) and rate limits (429)
                if ($statusCode >= 500 || $statusCode === 429) {
                    $attempts++;
                    if ($attempts > $this->maxRetries) {
                        break;
                    }
                    usleep($this->retryDelayMs * 1000 * pow(2, $attempts - 1));
                    continue;
                }

                if ($res) {
                    $decoded = json_decode($res->getBody()->getContents(), true);
                    $message = $decoded['error'] ?? $decoded['message'] ?? "HTTP {$statusCode}";
                    throw new PrintHubException("PrintHubClient error: {$message} [{$statusCode}]", $statusCode, $e);
                }
                throw new PrintHubConnectionException("PrintHubClient connection error: " . $e->getMessage(), 0, $e);
            }
        }

        // If we got here, all retries were exhausted on a server error
        $res = $lastException instanceof RequestException ? $lastException->getResponse() : null;
        if ($res) {
            $decoded = json_decode($res->getBody()->getContents(), true);
            $message = $decoded['error'] ?? "HTTP " . $res->getStatusCode();
            throw new PrintHubException("PrintHubClient error after {$attempts} attempts: {$message} [{$res->getStatusCode()}]");
        }
        throw new PrintHubConnectionException("PrintHubClient connection error after {$attempts} attempts");
    }

    private function resolveValue(string $key, array $data)
    {
        $keys = explode('.', $key);
        $val = $data;
        foreach ($keys as $k) {
            if (isset($val[$k])) {
                $val = $val[$k];
            } else {
                return null;
            }
        }
        return $val;
    }

    // =========================================================================
    // Schema Management — Extended
    // =========================================================================

    /**
     * List all registered data schemas.
     *
     * @return array  ['schemas' => [...]]
     */
    public function listSchemas(): array
    {
        return $this->get('api/v1/schemas');
    }

    /**
     * Get version history for a named schema.
     *
     * @param string $name  Schema name
     * @return array  ['versions' => [...]]
     */
    public function schemaVersions(string $name): array
    {
        return $this->get("api/v1/schema/{$name}/versions");
    }

    /**
     * Get the diff between two versions of a schema.
     *
     * @param string $name        Schema name
     * @param int    $fromVersion  Source version
     * @param int    $toVersion    Target version
     * @return array  { schema_name, from_version, to_version, changes }
     */
    public function schemaVersionDiff(string $name, int $fromVersion, int $toVersion): array
    {
        return $this->get("api/v1/schemas/{$name}/diff?from={$fromVersion}&to={$toVersion}");
    }

    /**
     * Validate data against a template's schema (server-side).
     *
     * @param string $templateName  Template name
     * @param array  $data          Data to validate
     * @return array  { valid, errors }
     */
    public function validateTemplateData(string $templateName, array $data): array
    {
        return $this->post("api/v1/templates/{$templateName}/validate", ['data' => $data]);
    }

    // =========================================================================
    // Health
    // =========================================================================

    /**
     * Check the health of the Print Hub server.
     *
     * @return array  { status, version, uptime, ... }
     */
    public function health(): array
    {
        return $this->get('api/v1/health');
    }

    // =========================================================================
    // Document Management
    // =========================================================================

    /**
     * Upload a document to Print Hub for later use in print jobs.
     *
     * @param string      $filename    Original filename
     * @param string      $base64Data  Base64-encoded file content
     * @param string|null $mimeType    MIME type (e.g. application/pdf)
     * @return array  { id, filename, mime_type, size, url, created_at }
     */
    public function uploadDocument(string $filename, string $base64Data, ?string $mimeType = null): array
    {
        return $this->post('api/v1/documents/upload', [
            'filename'   => $filename,
            'file_data'  => $base64Data,
            'mime_type'  => $mimeType,
        ]);
    }

    /**
     * List all uploaded documents.
     *
     * @return array  { data: [...], meta: { ... } }
     */
    public function listDocuments(): array
    {
        return $this->get('api/v1/documents');
    }

    /**
     * Get details of a specific document.
     *
     * @param int $id  Document ID
     * @return array
     */
    public function getDocument(int $id): array
    {
        return $this->get("api/v1/documents/{$id}");
    }

    /**
     * Preview a document (rendered as PDF).
     *
     * @param int $id  Document ID
     * @return string  Raw PDF binary content
     */
    public function previewDocument(int $id): string
    {
        try {
            $response = $this->http->get("api/v1/documents/{$id}/preview");
            return $response->getBody()->getContents();
        } catch (RequestException $e) {
            $res = $e->getResponse();
            if ($res) {
                $decoded = json_decode($res->getBody()->getContents(), true);
                $message = $decoded['error'] ?? "HTTP " . $res->getStatusCode();
                throw new PrintHubException("Document preview failed: {$message}");
            }
            throw new PrintHubConnectionException("Document preview connection error: " . $e->getMessage());
        }
    }

    /**
     * Download a document's raw file content.
     *
     * @param int $id  Document ID
     * @return string  Raw file binary content
     */
    public function downloadDocument(int $id): string
    {
        try {
            $response = $this->http->get("api/v1/documents/{$id}/download");
            return $response->getBody()->getContents();
        } catch (RequestException $e) {
            $res = $e->getResponse();
            if ($res) {
                $decoded = json_decode($res->getBody()->getContents(), true);
                $message = $decoded['error'] ?? "HTTP " . $res->getStatusCode();
                throw new PrintHubException("Document download failed: {$message}");
            }
            throw new PrintHubConnectionException("Document download connection error: " . $e->getMessage());
        }
    }

    /**
     * Delete a document.
     *
     * @param int $id  Document ID
     * @return array  { message }
     */
    public function deleteDocument(int $id): array
    {
        return $this->delete("api/v1/documents/{$id}");
    }

    // =========================================================================
    // Approvals
    // =========================================================================

    /**
     * List all print jobs pending approval.
     *
     * @return array  { data: [...], meta: { ... } }
     */
    public function getPendingApprovals(): array
    {
        return $this->get('api/v1/approvals/pending');
    }

    /**
     * Approve a pending print job.
     *
     * @param string $jobId  Job UUID
     * @return array  { success, message, job }
     */
    public function approveJob(string $jobId): array
    {
        return $this->post("api/v1/approvals/{$jobId}/approve", []);
    }

    /**
     * Reject a pending print job.
     *
     * @param string $jobId  Job UUID
     * @return array  { success, message, job }
     */
    public function rejectJob(string $jobId): array
    {
        return $this->post("api/v1/approvals/{$jobId}/reject", []);
    }

    // =========================================================================
    // Agent Version
    // =========================================================================

    /**
     * Get the latest available TrayPrint agent version.
     *
     * @return array  { version, download_url, release_notes, published_at }
     */
    public function getAgentVersion(): array
    {
        return $this->get('api/v1/agents/version');
    }

    // =========================================================================
    // Fonts
    // =========================================================================

    /**
     * List all available fonts.
     *
     * @return array  { data: [...], meta: { ... } }
     */
    public function getFonts(): array
    {
        return $this->get('api/v1/fonts');
    }

    // =========================================================================
    // Formula Editor
    // =========================================================================

    /**
     * List all available formula functions.
     *
     * @return array  { functions: [...] }
     */
    public function getFormulaFunctions(): array
    {
        return $this->get('api/v1/formula/functions');
    }

    /**
     * Validate a formula expression.
     *
     * @param string $expression  The formula expression to validate
     * @return array  { valid, errors, tokens }
     */
    public function validateFormula(string $expression): array
    {
        return $this->post('api/v1/formula/validate', ['expression' => $expression]);
    }

    /**
     * Evaluate a formula expression with given context.
     *
     * @param string $expression  The formula expression to evaluate
     * @param array  $context     Variable context for evaluation
     * @return array  { result, success, error }
     */
    public function evaluateFormula(string $expression, array $context = []): array
    {
        return $this->post('api/v1/formula/evaluate', [
            'expression' => $expression,
            'context'    => $context,
        ]);
    }
}
