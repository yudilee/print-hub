<?php

namespace Tests\Feature;

use App\Models\ClientApp;
use App\Models\PrintAgent;
use App\Models\PrintTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class BatchPrintTest extends TestCase
{
    use RefreshDatabase;

    protected ClientApp $clientApp;
    protected PrintAgent $agent;
    protected PrintTemplate $template;
    protected string $rawApiKey;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rawApiKey = '550e8400-e29b-41d4-a716-446655440000';

        // Use sha256 hash for api_key because ClientApp::findByKey() first
        // tries a sha256 lookup against the api_key column.
        $this->clientApp = ClientApp::create([
            'name'      => 'Test App',
            'api_key'   => hash('sha256', $this->rawApiKey),
            'is_active' => true,
        ]);

        $this->agent = PrintAgent::create([
            'name'         => 'Test Agent',
            'agent_key'    => hash('sha256', Str::random(32)),
            'is_active'    => true,
            'ip_address'   => '127.0.0.1',
            'last_seen_at' => now(),
            'printers'     => ['Test Printer'],
        ]);

        $this->template = PrintTemplate::create([
            'name'            => 'test_template',
            'paper_width_mm'  => 210,
            'paper_height_mm' => 297,
            'elements'        => [
                ['type' => 'label', 'text' => 'Hello', 'x' => 10, 'y' => 10],
            ],
        ]);
    }

    protected function apiHeaders(): array
    {
        return ['X-API-Key' => $this->rawApiKey];
    }

    public function test_batch_print_accepts_array_of_jobs()
    {
        $response = $this->withHeaders($this->apiHeaders())
            ->postJson('/api/v1/print/batch', [
                'jobs' => [
                    [
                        'template' => 'test_template',
                        'data'     => [],
                        'printer'  => 'Test Printer',
                    ],
                    [
                        'template' => 'test_template',
                        'data'     => [],
                        'printer'  => 'Test Printer',
                    ],
                ],
            ]);

        // Batch endpoint returns 202 Accepted for async processing
        $response->assertStatus(202);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure([
            'data' => [
                'batch_id',
                'results' => [
                    '*' => ['index', 'success', 'job_id', 'reference'],
                ],
            ],
        ]);
    }

    public function test_batch_creates_multiple_jobs()
    {
        $response = $this->withHeaders($this->apiHeaders())
            ->postJson('/api/v1/print/batch', [
                'jobs' => [
                    [
                        'template' => 'test_template',
                        'data'     => [],
                        'printer'  => 'Test Printer',
                    ],
                    [
                        'template' => 'test_template',
                        'data'     => [],
                        'printer'  => 'Test Printer',
                    ],
                ],
            ]);

        // Batch endpoint returns 202 Accepted for async processing
        $response->assertStatus(202);

        // Two print jobs should have been created
        $this->assertEquals(2, \App\Models\PrintJob::count());
    }

    public function test_batch_returns_proper_response_structure()
    {
        $response = $this->withHeaders($this->apiHeaders())
            ->postJson('/api/v1/print/batch', [
                'jobs' => [
                    [
                        'template' => 'test_template',
                        'data'     => [],
                        'printer'  => 'Test Printer',
                        'reference_id' => 'ref-001',
                    ],
                ],
            ]);

        // Batch endpoint returns 202 Accepted for async processing
        $response->assertStatus(202)
            ->assertJsonStructure([
                'data' => [
                    'batch_id',
                    'results' => [
                        '*' => [
                            'index',
                            'success',
                            'job_id',
                            'reference',
                        ],
                    ],
                ],
            ]);

        $response->assertJsonPath('data.results.0.reference', 'ref-001');
        $response->assertJsonPath('data.results.0.success', true);
    }

    public function test_batch_validates_empty_jobs_array()
    {
        $response = $this->withHeaders($this->apiHeaders())
            ->postJson('/api/v1/print/batch', [
                'jobs' => [],
            ]);

        $response->assertStatus(422);
    }

    public function test_batch_validates_missing_jobs_field()
    {
        $response = $this->withHeaders($this->apiHeaders())
            ->postJson('/api/v1/print/batch', []);

        $response->assertStatus(422);
    }

    public function test_batch_dry_run_does_not_create_jobs()
    {
        $response = $this->withHeaders($this->apiHeaders())
            ->postJson('/api/v1/print/batch', [
                'jobs' => [
                    [
                        'template' => 'test_template',
                        'data'     => [],
                        'printer'  => 'Test Printer',
                    ],
                ],
                'dry_run' => true,
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.dry_run', true);
        $response->assertJsonPath('data.all_valid', true);

        // No jobs should have been created
        $this->assertEquals(0, \App\Models\PrintJob::count());
    }

    public function test_batch_with_reference_ids()
    {
        $response = $this->withHeaders($this->apiHeaders())
            ->postJson('/api/v1/print/batch', [
                'jobs' => [
                    [
                        'template' => 'test_template',
                        'data'     => [],
                        'printer'  => 'Test Printer',
                        'reference_id' => 'order-123',
                    ],
                    [
                        'template' => 'test_template',
                        'data'     => [],
                        'printer'  => 'Test Printer',
                        'reference_id' => 'order-456',
                    ],
                ],
            ]);

        // Batch endpoint returns 202 Accepted for async processing
        $response->assertStatus(202);

        $this->assertDatabaseHas('print_jobs', ['reference_id' => 'order-123']);
        $this->assertDatabaseHas('print_jobs', ['reference_id' => 'order-456']);
    }

    public function test_batch_without_template_or_document_returns_validation_error()
    {
        $response = $this->withHeaders($this->apiHeaders())
            ->postJson('/api/v1/print/batch', [
                'jobs' => [
                    [
                        'printer' => 'Test Printer',
                        // No template, no document_base64
                    ],
                ],
            ]);

        $response->assertStatus(422);
    }

    public function test_batch_max_50_jobs()
    {
        $jobs = [];
        for ($i = 0; $i < 51; $i++) {
            $jobs[] = [
                'template' => 'test_template',
                'data'     => [],
                'printer'  => 'Test Printer',
            ];
        }

        $response = $this->withHeaders($this->apiHeaders())
            ->postJson('/api/v1/print/batch', [
                'jobs' => $jobs,
            ]);

        $response->assertStatus(422);
    }
}
