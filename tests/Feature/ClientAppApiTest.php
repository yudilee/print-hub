<?php

namespace Tests\Feature;

use App\Models\ClientApp;
use App\Models\PrintAgent;
use App\Models\PrintTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientAppApiTest extends TestCase
{
    use RefreshDatabase;

    protected ClientApp $clientApp;
    protected PrintAgent $agent;
    protected PrintTemplate $template;
    protected string $rawApiKey;
    protected string $rawAgentKey;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rawApiKey = '550e8400-e29b-41d4-a716-446655440000';
        $this->rawAgentKey = 'test-agent-key-32-chars-long!!';

        $this->clientApp = ClientApp::create([
            'name'       => 'Test App',
            'api_key'    => ClientApp::hashKey($this->rawApiKey),
            'is_active'  => true,
        ]);

        $this->agent = PrintAgent::create([
            'name'         => 'Test Agent',
            'agent_key'    => PrintAgent::hashKey($this->rawAgentKey),
            'is_active'    => true,
            'ip_address'   => '127.0.0.1',
            'last_seen_at' => now(),
            'printers'     => ['Test Printer'],
        ]);

        $this->template = PrintTemplate::create([
            'name'            => 'test_template',
            'paper_width_mm'  => 210,
            'paper_height_mm' => 297,
            'elements'        => [],
        ]);
    }

    protected function apiHeaders(): array
    {
        return ['X-API-Key' => $this->rawApiKey];
    }

    public function test_connection_returns_app_info()
    {
        $response = $this->withHeaders($this->apiHeaders())
            ->getJson('/api/v1/test');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data'    => [
                    'app_name' => 'Test App',
                ],
            ]);
    }

    public function test_unauthorized_requests_are_rejected()
    {
        $response = $this->withHeaders(['X-API-Key' => 'invalid-key'])
            ->getJson('/api/v1/test');

        $response->assertUnauthorized();
    }

    public function test_list_templates_returns_template_list()
    {
        $response = $this->withHeaders($this->apiHeaders())
            ->getJson('/api/v1/templates');

        $response->assertOk()
            ->assertJsonPath('data.templates.0.name', 'test_template');
    }

    public function test_get_single_template_returns_template_details()
    {
        $response = $this->withHeaders($this->apiHeaders())
            ->getJson('/api/v1/templates/test_template');

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'name'            => 'test_template',
                    'paper_width_mm'  => 210,
                    'paper_height_mm' => 297,
                ],
            ]);
    }

    public function test_get_template_schema_returns_required_fields()
    {
        $this->template->update([
            'elements' => [
                ['type' => 'field', 'key' => 'customer_name', 'x' => 10, 'y' => 10],
            ],
        ]);

        $response = $this->withHeaders($this->apiHeaders())
            ->getJson('/api/v1/templates/test_template/schema');

        $response->assertOk()
            ->assertJsonPath('data.required_fields.customer_name.label', 'customer_name');
    }

    public function test_preview_generates_pdf()
    {
        $this->template->update([
            'elements' => [
                ['type' => 'label', 'text' => 'Hello', 'x' => 10, 'y' => 10],
            ],
        ]);

        $response = $this->withHeaders($this->apiHeaders())
            ->postJson('/api/v1/preview', [
                'template' => 'test_template',
                'data'     => [],
            ]);

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_unified_print_with_template_queues_a_job()
    {
        $this->template->update([
            'elements' => [
                ['type' => 'label', 'text' => 'Test', 'x' => 10, 'y' => 10],
            ],
        ]);

        $response = $this->withHeaders($this->apiHeaders())
            ->postJson('/api/v1/print', [
                'template' => 'test_template',
                'data'     => [],
                'agent_id' => $this->agent->id,
                'printer'  => 'Test Printer',
            ]);

        $response->assertStatus(202)
            ->assertJson([
                'data' => [
                    'status'   => 'queued',
                    'agent'    => 'Test Agent',
                    'printer'  => 'Test Printer',
                    'template' => 'test_template',
                ],
            ]);
    }

    public function test_unified_print_without_template_or_document_returns_422()
    {
        $response = $this->withHeaders($this->apiHeaders())
            ->postJson('/api/v1/print', []);

        $response->assertStatus(422);
    }

    public function test_unified_print_without_online_agent_returns_503()
    {
        // Make all agents appear offline
        $this->agent->update(['last_seen_at' => now()->subMinutes(10)]);

        $this->template->update([
            'elements' => [
                ['type' => 'label', 'text' => 'Test', 'x' => 10, 'y' => 10],
            ],
        ]);

        $response = $this->withHeaders($this->apiHeaders())
            ->postJson('/api/v1/print', [
                'template' => 'test_template',
                'data'     => [],
                'printer'  => 'Test Printer',
            ]);

        $response->assertStatus(503)
            ->assertJson(['error' => ['message' => 'No online agent available.']]);
    }

    public function test_legacy_submit_job_delegates_to_unified_print()
    {
        $this->template->update([
            'elements' => [
                ['type' => 'label', 'text' => 'Legacy', 'x' => 10, 'y' => 10],
            ],
        ]);

        $response = $this->withHeaders($this->apiHeaders())
            ->postJson('/api/v1/jobs', [
                'template'      => 'test_template',
                'template_data' => [],
                'agent_id'      => $this->agent->id,
                'printer'       => 'Test Printer',
            ]);

        $response->assertStatus(202)
            ->assertJson([
                'data' => [
                    'status' => 'queued',
                    'agent'  => 'Test Agent',
                ],
            ]);
    }

    public function test_job_status_returns_job_info()
    {
        $job = \App\Models\PrintJob::create([
            'job_id'         => 'test-job-uuid',
            'print_agent_id' => $this->agent->id,
            'printer_name'   => 'Test Printer',
            'type'           => 'pdf',
            'status'         => 'pending',
            'file_path'      => 'print_jobs/test.pdf',
        ]);

        $response = $this->withHeaders($this->apiHeaders())
            ->getJson('/api/v1/jobs/test-job-uuid');

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'job_id'  => 'test-job-uuid',
                    'status'  => 'pending',
                    'printer' => 'Test Printer',
                ],
            ]);
    }

    public function test_job_status_returns_404_for_unknown_job()
    {
        $response = $this->withHeaders($this->apiHeaders())
            ->getJson('/api/v1/jobs/nonexistent');

        $response->assertNotFound();
    }
}
