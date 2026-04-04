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
 *   // List templates
 *   $templates = $hub->getTemplates();
 *
 *   // Check job status
 *   $status = $hub->jobStatus($result['job_id']);
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

    /**
     * List all available templates with their field schemas.
     */
    public function getTemplates(): array
    {
        return $this->get('/api/v1/templates')['templates'] ?? [];
    }

    /**
     * Get a single template's field schema by name.
     */
    public function getTemplate(string $name): array
    {
        return $this->get("/api/v1/templates/{$name}");
    }

    // -------------------------------------------------------------------------
    // Print Methods
    // -------------------------------------------------------------------------

    /**
     * Print using a named template with data.
     *
     * @param  string  $template     Template name (as configured in Print Hub)
     * @param  array   $data         The data to fill the template fields
     * @param  string  $referenceId  Optional reference for tracking (e.g. invoice number)
     * @param  array   $options      Optional: ['agent_id', 'printer', 'webhook_url']
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
     * @param  string  $referenceId  Optional reference for tracking
     * @param  array   $options      Optional: ['agent_id', 'printer', 'webhook_url']
     */
    public function printRawPdf(
        string $base64Pdf,
        string $referenceId = '',
        array  $options = []
    ): array {
        // Clean base64 string
        $base64Pdf = preg_replace('/\s+/', '', $base64Pdf);
        
        // Ensure proper padding
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

    /**
     * Check the status of a print job.
     *
     * @param  string $jobId  UUID returned from a print call
     */
    public function jobStatus(string $jobId): array
    {
        return $this->get("/api/v1/jobs/{$jobId}");
    }

    /**
     * Wait for a job to complete (polling).
     *
     * @param  string $jobId       UUID returned from a print call
     * @param  int    $maxSeconds  Maximum seconds to wait
     * @param  int    $interval    Poll interval in seconds
     */
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

    /**
     * Get a list of currently online print agents.
     */
    public function getOnlineAgents(): array
    {
        return $this->get('/api/v1/agents/online')['agents'] ?? [];
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
}
