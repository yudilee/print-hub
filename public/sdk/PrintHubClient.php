<?php

/**
 * PrintHubClient — Drop-in PHP SDK for Print Hub
 *
 * Usage:
 *   require 'PrintHubClient.php';
 *   $hub = new PrintHubClient('http://print-hub:8082', 'your-api-key');
 *
 *   // Print with a template
 *   $result = $hub->printWithTemplate('invoice-rental', $data, 'INV-001');
 *
 *   // Print a raw PDF
 *   $result = $hub->printRawPdf(base64_encode($pdfString));
 *
 *   // Discover what data a template needs
 *   $schema = $hub->getTemplateSchema('invoice-rental');
 *
 *   // Register a data schema (versioned)
 *   $hub->registerSchema('invoice_rental', [
 *       'label'  => 'Rental Invoice',
 *       'fields' => [
 *           'invoice_number' => ['label' => 'Invoice No', 'type' => 'string', 'required' => true],
 *           'total_amount'   => ['label' => 'Total', 'type' => 'number', 'format' => 'currency', 'currency_code' => 'IDR'],
 *       ],
 *       'tables' => [
 *           'items' => [
 *               'label' => 'Line Items',
 *               'columns' => [
 *                   'description' => ['label' => 'Description', 'type' => 'string'],
 *                   'qty'         => ['label' => 'Qty', 'type' => 'number', 'format' => 'integer'],
 *                   'subtotal'    => ['label' => 'Subtotal', 'type' => 'number', 'format' => 'currency', 'computed' => 'qty * unit_price'],
 *               ],
 *           ],
 *       ],
 *       'sample_data' => [...],
 *   ]);
 */
class PrintHubClient
{
    private string $baseUrl;
    private string $apiKey;
    private int $timeout;

    public function __construct(string $baseUrl, string $apiKey, int $timeout = 15)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->apiKey  = $apiKey;
        $this->timeout = $timeout;
    }

    // -------------------------------------------------------------------------
    // Template Discovery
    // -------------------------------------------------------------------------

    /** List all available templates with their field schemas. */
    public function getTemplates(): array
    {
        return $this->get('/api/v1/templates')['templates'] ?? [];
    }

    /** Get a single template's field schema by name. */
    public function getTemplate(string $name): array
    {
        return $this->get("/api/v1/templates/{$name}");
    }

    /**
     * Get the required data schema for a template (bidirectional discovery).
     * Returns: required_fields, required_tables, sample_data.
     */
    public function getTemplateSchema(string $name): array
    {
        return $this->get("/api/v1/templates/{$name}/schema");
    }

    // -------------------------------------------------------------------------
    // Schema Registration (Versioned)
    // -------------------------------------------------------------------------

    /**
     * Register or update a data schema with Print Hub.
     * Creates a new version only when fields/tables structure changes.
     *
     * @param string $schemaName Unique identifier (e.g. 'invoice_rental')
     * @param array  $schemaData Keys: 'label', 'fields', 'tables', 'sample_data'
     */
    public function registerSchema(string $schemaName, array $schemaData): array
    {
        $payload = array_merge(['schema_name' => $schemaName], $schemaData);
        return $this->post('/api/v1/schema', $payload);
    }

    /** Get version history for a schema. */
    public function getSchemaVersions(string $schemaName): array
    {
        return $this->get("/api/v1/schema/{$schemaName}/versions");
    }

    /** List all schemas (latest versions by default). */
    public function listSchemas(bool $allVersions = false): array
    {
        $query = $allVersions ? '?latest=false' : '';
        return $this->get("/api/v1/schemas{$query}")['schemas'] ?? [];
    }

    // -------------------------------------------------------------------------
    // Data Validation (Client-Side)
    // -------------------------------------------------------------------------

    /**
     * Validate data against a template's required schema before printing.
     * Returns array of error messages (empty = valid).
     *
     * @param string $templateName   Template to validate against
     * @param array  $data           The data to validate
     */
    public function validateData(string $templateName, array $data): array
    {
        $errors = [];
        $schema = $this->getTemplateSchema($templateName);

        // Validate required fields
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

        // Validate tables
        foreach ($schema['required_tables'] ?? [] as $tableKey => $tableMeta) {
            $rows = $this->resolveValue($tableKey, $data);
            if ($rows !== null && !is_array($rows)) {
                $errors[] = "Table '{$tableKey}' expected array of rows.";
            }
        }

        return $errors;
    }

    // -------------------------------------------------------------------------
    // Print Methods
    // -------------------------------------------------------------------------

    /**
     * Print using a named template with data.
     *
     * @param  string  $template     Template name
     * @param  array   $data         Data to fill the template
     * @param  string  $referenceId  Optional reference (e.g. invoice number)
     * @param  array   $options      Optional: ['agent_id', 'printer', 'webhook_url', 'skip_validation']
     */
    public function printWithTemplate(
        string $template,
        array  $data,
        string $referenceId = '',
        array  $options = []
    ): array {
        $payload = array_merge([
            'template'     => $template,
            'data'         => $data,
            'reference_id' => $referenceId ?: null,
        ], $options);

        return $this->post('/api/v1/print', $payload);
    }

    /**
     * Print a raw PDF document (no template needed).
     *
     * @param  string  $base64Pdf    Base64-encoded PDF string
     * @param  string  $referenceId  Optional reference
     * @param  array   $options      Optional: ['agent_id', 'printer', 'webhook_url']
     */
    public function printRawPdf(
        string $base64Pdf,
        string $referenceId = '',
        array  $options = []
    ): array {
        $base64Pdf = preg_replace('/\s+/', '', $base64Pdf);
        $padding = strlen($base64Pdf) % 4;
        if ($padding > 0 && $padding !== 1) {
            $base64Pdf = str_pad($base64Pdf, strlen($base64Pdf) + (4 - $padding), '=', STR_PAD_RIGHT);
        }
        if (strlen($base64Pdf) % 4 === 1) {
            throw new RuntimeException("Invalid base64 string length for PDF document.");
        }

        $payload = array_merge([
            'document_base64' => $base64Pdf,
            'reference_id'    => $referenceId ?: null,
        ], $options);

        return $this->post('/api/v1/print', $payload);
    }

    // -------------------------------------------------------------------------
    // Job Status
    // -------------------------------------------------------------------------

    /** Check the status of a print job. */
    public function jobStatus(string $jobId): array
    {
        return $this->get("/api/v1/jobs/{$jobId}");
    }

    /** Wait for a job to complete (polling). */
    public function waitForJob(string $jobId, int $maxSeconds = 30, int $interval = 2): array
    {
        $elapsed = 0;
        while ($elapsed < $maxSeconds) {
            $status = $this->jobStatus($jobId);
            if (in_array($status['status'] ?? '', ['completed', 'failed', 'error'])) {
                return $status;
            }
            sleep($interval);
            $elapsed += $interval;
        }
        return ['status' => 'timeout', 'job_id' => $jobId];
    }

    // -------------------------------------------------------------------------
    // Agent Discovery
    // -------------------------------------------------------------------------

    /** Get a list of currently online print agents. */
    public function getOnlineAgents(): array
    {
        return $this->get('/api/v1/agents/online')['agents'] ?? [];
    }

    /** Test connection to Print Hub. */
    public function testConnection(): array
    {
        return $this->get('/api/v1/test');
    }

    // -------------------------------------------------------------------------
    // HTTP Internals
    // -------------------------------------------------------------------------

    private function get(string $path): array
    {
        return $this->request('GET', $path);
    }

    private function post(string $path, array $body): array
    {
        return $this->request('POST', $path, $body);
    }

    private function request(string $method, string $path, array $body = []): array
    {
        $url = $this->baseUrl . $path;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_HTTPHEADER     => [
                'X-API-Key: ' . $this->apiKey,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw new RuntimeException("PrintHubClient cURL error: {$curlError}");
        }

        $decoded = json_decode($response, true);

        if ($httpCode >= 400) {
            $message = $decoded['error'] ?? "HTTP {$httpCode}";
            throw new RuntimeException("PrintHubClient error: {$message} [{$httpCode}]");
        }

        return $decoded ?? [];
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

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
