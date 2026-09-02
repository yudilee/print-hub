<?php

namespace Tests\Feature;

use App\Models\ClientApp;
use App\Models\Connector;
use App\Models\PrintAgent;
use App\Models\PrintJob;
use App\Models\Setting;
use App\Services\OdooConnectorService;
use App\Services\PrintJobOrchestrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OdooAndReprintFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected ClientApp $clientApp;
    protected PrintAgent $agent;
    protected string $rawApiKey;
    protected string $rawAgentKey;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();

        $this->rawApiKey = 'odoo-test-key-550e8400-e29b-41d4';
        $this->rawAgentKey = 'agent-test-key-32-chars-long!!';

        $this->clientApp = ClientApp::create([
            'name'      => 'Odoo ERP Client',
            'api_key'   => hash('sha256', $this->rawApiKey),
            'is_active' => true,
        ]);

        $this->agent = PrintAgent::create([
            'name'         => 'Main Office Agent',
            'agent_key'    => hash('sha256', $this->rawAgentKey),
            'is_active'    => true,
            'ip_address'   => '127.0.0.1',
            'last_seen_at' => now(),
            'printers'     => ['EPSON-LQ2190', 'HP-LaserJet'],
        ]);
    }

    public function test_odoo_connector_service_authentication_and_test_connection()
    {
        Http::fake([
            'http://odoo.local:8069/web/session/authenticate' => Http::response([
                'jsonrpc' => '2.0',
                'id'      => 1,
                'result'  => [
                    'uid'            => 2,
                    'server_version' => '19.0',
                ],
            ], 200, ['Set-Cookie' => 'session_id=fake_session_12345; Path=/;']),
        ]);

        $service = new OdooConnectorService();
        $auth = $service->authenticate('http://odoo.local:8069', 'test_db', 'admin', 'admin');

        $this->assertTrue($auth['success']);
        $this->assertEquals(2, $auth['uid']);
        $this->assertEquals('19.0', $auth['server_version']);

        $connector = Connector::create([
            'client_app_id' => $this->clientApp->id,
            'name'          => 'Production Odoo',
            'type'          => 'odoo',
            'config'        => [
                'url'      => 'http://odoo.local:8069',
                'db'       => 'test_db',
                'login'    => 'admin',
                'password' => 'admin',
            ],
            'is_active'     => true,
        ]);

        $testResult = $connector->testConnection();
        $this->assertTrue($testResult['success']);
        $this->assertStringContainsString('Connected to Odoo 19.0', $testResult['message']);
    }

    public function test_print_odoo_report_endpoint()
    {
        $dummyPdf = '%PDF-1.4 dummy pdf content for odoo report testing %%EOF';

        Http::fake([
            'http://odoo.local:8069/web/session/authenticate' => Http::response([
                'jsonrpc' => '2.0',
                'result'  => [
                    'uid'            => 2,
                    'server_version' => '19.0',
                ],
            ], 200),
            'http://odoo.local:8069/jsonrpc' => Http::response([
                'jsonrpc' => '2.0',
                'result'  => [$dummyPdf, 'pdf'],
            ], 200),
        ]);

        $connector = Connector::create([
            'client_app_id' => $this->clientApp->id,
            'name'          => 'Warehouse Odoo',
            'type'          => 'odoo',
            'config'        => [
                'url'      => 'http://odoo.local:8069',
                'db'       => 'test_db',
                'login'    => 'admin',
                'password' => 'admin',
            ],
            'is_active'     => true,
        ]);

        $response = $this->withHeaders(['X-API-Key' => $this->rawApiKey])
            ->postJson('/api/v1/print/odoo-report', [
                'connector_id' => $connector->id,
                'report_name'  => 'stock.report_deliveryslip',
                'record_ids'   => [42, 43],
                'printer'      => 'EPSON-LQ2190',
                'options'      => ['copies' => 2],
                'reference_id' => 'stock.picking,42',
            ]);

        $response->assertStatus(202);
        $response->assertJson([
            'success' => true,
            'data'    => [
                'status'  => 'queued',
                'printer' => 'EPSON-LQ2190',
            ],
        ]);

        $jobId = $response->json('data.job_id');
        $this->assertDatabaseHas('print_jobs', [
            'job_id'       => $jobId,
            'reference_id' => 'stock.picking,42',
            'printer_name' => 'EPSON-LQ2190',
        ]);
    }

    public function test_reprint_job_via_api()
    {
        $originalJob = PrintJob::create([
            'job_id'         => 'orig-job-uuid-1111',
            'print_agent_id' => $this->agent->id,
            'printer_name'   => 'HP-LaserJet',
            'type'           => 'pdf',
            'priority'       => 5,
            'status'         => 'success',
            'options'        => ['copies' => 3],
            'reference_id'   => 'INV-999',
        ]);

        $response = $this->withHeaders(['X-API-Key' => $this->rawApiKey])
            ->postJson("/api/v1/jobs/{$originalJob->job_id}/reprint");

        $response->assertStatus(202);
        $response->assertJson([
            'success' => true,
            'data'    => [
                'status'          => 'queued',
                'original_job_id' => 'orig-job-uuid-1111',
                'printer'         => 'HP-LaserJet',
            ],
        ]);

        $newJobId = $response->json('data.job_id');
        $this->assertNotEquals('orig-job-uuid-1111', $newJobId);

        $this->assertDatabaseHas('print_jobs', [
            'job_id'              => $newJobId,
            'retried_from_job_id' => $originalJob->id,
            'retry_count'         => 1,
            'status'              => 'pending',
            'reference_id'        => 'INV-999',
        ]);
    }

    public function test_job_expiration_via_process_scheduled_jobs()
    {
        $expiredJob = PrintJob::create([
            'job_id'         => 'expired-job-1234',
            'print_agent_id' => $this->agent->id,
            'printer_name'   => 'HP-LaserJet',
            'type'           => 'pdf',
            'status'         => 'pending',
            'expires_at'     => now()->subMinutes(10),
        ]);

        $activeJob = PrintJob::create([
            'job_id'         => 'active-job-5678',
            'print_agent_id' => $this->agent->id,
            'printer_name'   => 'HP-LaserJet',
            'type'           => 'pdf',
            'status'         => 'pending',
            'expires_at'     => now()->addHour(),
        ]);

        $this->artisan('print-hub:process-scheduled')
            ->assertSuccessful();

        $this->assertEquals('expired', $expiredJob->fresh()->status);
        $this->assertEquals('pending', $activeJob->fresh()->status);
    }
}
