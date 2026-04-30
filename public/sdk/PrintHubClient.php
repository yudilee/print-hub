<?php

use GuzzleHttp\Client;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Exception\RequestException;

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
 * preview, batch printing, and job polling.
 *
 * @version 2.0
 */
class PrintHubClient
{
    private Client $http;
    private string $cacheDir;
    private ?string $defaultBranchCode = null;

    /**
     * Create a new PrintHubClient instance.
     *
     * @param string $baseUrl   The Print Hub server URL (e.g. https://print-hub.example.com)
     * @param string $apiKey    Your client app API key (from Print Hub > Client Apps)
     * @param int    $timeout   Request timeout in seconds
     * @param string $cacheDir  Directory for caching schema data
     */
    public function __construct(string $baseUrl, string $apiKey, int $timeout = 15, string $cacheDir = '/tmp')
    {
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
     */
    public function getQueues(): array
    {
        return $this->get('api/v1/queues')['queues'] ?? [];
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
        $cacheFile = $this->cacheDir . '/printhub_schema_' . md5($name) . '.json';
        if ($useCache && file_exists($cacheFile) && filemtime($cacheFile) > (time() - 600)) {
            return json_decode(file_get_contents($cacheFile), true);
        }
        $schema = $this->get("api/v1/templates/{$name}/schema");
        file_put_contents($cacheFile, json_encode($schema));
        return $schema;
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
     * @param string      $template     Template name (e.g. "invoice_sewa")
     * @param array       $data         Data to fill into the template
     * @param string      $referenceId  Your reference ID for tracking
     * @param string      $queue        Queue/profile name override (optional)
     * @param string|null $branchCode   Branch code override (or uses default)
     * @param array       $options      Additional options (skip_validation, copies, etc.)
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
        array  $options = []
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

        return $this->post('api/v1/print', $payload);
    }

    /**
     * Print using a named template (asynchronous / non-blocking).
     *
     * Returns a Guzzle Promise. Resolve with ->wait() or use ->then().
     */
    public function printAsync(
        string $template,
        array  $data,
        string $referenceId = '',
        string $queue = '',
        ?string $branchCode = null,
        array  $options = []
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
     * @param string      $base64Pdf    Base64-encoded PDF content
     * @param string      $referenceId  Your reference ID for tracking
     * @param string      $queue        Queue/profile name override
     * @param string|null $branchCode   Branch code override (or uses default)
     * @param array       $options      Additional options
     */
    public function printRawPdf(
        string $base64Pdf,
        string $referenceId = '',
        string $queue = '',
        ?string $branchCode = null,
        array  $options = []
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
     * @param string $template  Template name
     * @param array  $data      Template data
     * @param array  $options   Options (paper_size, orientation, etc.)
     * @return string  Raw PDF binary content
     */
    public function preview(string $template, array $data, array $options = []): string
    {
        $payload = [
            'template' => $template,
            'data'     => $data,
            'options'  => $options,
        ];

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

    private function request(string $method, string $path, array $options = []): array
    {
        try {
            $response = $this->http->request($method, $path, $options);
            return json_decode($response->getBody()->getContents(), true) ?? [];
        } catch (RequestException $e) {
            $res = $e->getResponse();
            if ($res) {
                $decoded = json_decode($res->getBody()->getContents(), true);
                $message = $decoded['error'] ?? $decoded['message'] ?? "HTTP " . $res->getStatusCode();
                throw new PrintHubException("PrintHubClient error: {$message} [{$res->getStatusCode()}]");
            }
            throw new PrintHubConnectionException("PrintHubClient connection error: " . $e->getMessage());
        }
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
}
