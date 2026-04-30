<?php

namespace Tests\Feature;

use App\Models\PrintAgent;
use App\Models\PrintJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PrintHubControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthorized_access()
    {
        $response = $this->getJson('/api/print-hub/profiles');
        $response->assertStatus(401);
    }

    public function test_get_profiles_success()
    {
        $agent = PrintAgent::create([
            'name' => 'Test Agent',
            'agent_key' => Str::random(32),
            'ip_address' => '127.0.0.1',
            'is_active' => true,
        ]);

        $response = $this->withHeader('X-Agent-Key', $agent->agent_key)
            ->getJson('/api/print-hub/profiles');

        $response->assertStatus(200);
        $response->assertJsonStructure(['profiles']);
    }

    public function test_report_job_status()
    {
        $agent = PrintAgent::create([
            'name' => 'Test Agent',
            'agent_key' => Str::random(32),
            'ip_address' => '127.0.0.1',
            'is_active' => true,
        ]);

        $job = PrintJob::create([
            'job_id' => Str::uuid(),
            'print_agent_id' => $agent->id,
            'printer_name' => 'Epson L3110',
            'type' => 'pdf',
            'status' => 'pending',
            'file_path' => 'test.pdf',
        ]);

        $response = $this->withHeader('X-Agent-Key', $agent->agent_key)
            ->postJson('/api/print-hub/jobs', [
                'job_id' => $job->job_id,
                'printer' => 'Epson L3110',
                'type' => 'pdf',
                'status' => 'success',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('print_jobs', [
            'job_id' => $job->job_id,
            'status' => 'success',
        ]);
    }
}
