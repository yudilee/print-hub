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
        $rawKey = Str::random(32);

        // Use sha256 hash because PrintAgent::findByKey() first tries
        // a sha256 lookup against the agent_key column.
        $agent = PrintAgent::create([
            'name' => 'Test Agent',
            'agent_key' => hash('sha256', $rawKey),
            'ip_address' => '127.0.0.1',
            'is_active' => true,
        ]);

        $response = $this->withHeader('X-Agent-Key', $rawKey)
            ->getJson('/api/print-hub/profiles');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => ['profiles'],
        ]);
    }

    public function test_report_job_status()
    {
        $rawKey = Str::random(32);

        $agent = PrintAgent::create([
            'name' => 'Test Agent',
            'agent_key' => hash('sha256', $rawKey),
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

        $response = $this->withHeader('X-Agent-Key', $rawKey)
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

    public function test_get_queue_leasing_and_robustness()
    {
        $rawKey = Str::random(32);

        $agent = PrintAgent::create([
            'name' => 'Test Agent',
            'agent_key' => hash('sha256', $rawKey),
            'ip_address' => '127.0.0.1',
            'is_active' => true,
        ]);

        $job = PrintJob::create([
            'job_id' => Str::uuid(),
            'print_agent_id' => $agent->id,
            'printer_name' => 'Epson L3110',
            'type' => 'pdf',
            'status' => 'pending',
            'approval_status' => 'auto_approved',
            'file_path' => 'test.pdf',
        ]);

        // 1. Pull the queue - job should be returned and leased
        $response = $this->withHeader('X-Agent-Key', $rawKey)
            ->getJson('/api/print-hub/queue');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data.jobs');

        // Confirm the job is now leased (status processing, dispatched_at is set)
        $job->refresh();
        $this->assertEquals('processing', $job->status);
        $this->assertNotNull($job->dispatched_at);

        // 2. Pull the queue again immediately - job should NOT be returned (already leased)
        $response2 = $this->withHeader('X-Agent-Key', $rawKey)
            ->getJson('/api/print-hub/queue');

        $response2->assertStatus(200);
        $response2->assertJsonCount(0, 'data.jobs');

        // 3. Simulate job lease expiration by setting dispatched_at back in time
        $job->update(['dispatched_at' => now()->subMinutes(6)]);

        // 4. Pull the queue again - job should be reverted to pending, re-leased, and returned
        $response3 = $this->withHeader('X-Agent-Key', $rawKey)
            ->getJson('/api/print-hub/queue');

        $response3->assertStatus(200);
        $response3->assertJsonCount(1, 'data.jobs');

        $job->refresh();
        $this->assertEquals('processing', $job->status);
        $this->assertTrue($job->dispatched_at->diffInSeconds(now()) < 5);
    }

    public function test_retry_job_lineage()
    {
        $rawKey = Str::random(32);

        $agent = PrintAgent::create([
            'name' => 'Test Agent',
            'agent_key' => hash('sha256', $rawKey),
            'ip_address' => '127.0.0.1',
            'is_active' => true,
        ]);

        $job = PrintJob::create([
            'job_id' => Str::uuid(),
            'print_agent_id' => $agent->id,
            'printer_name' => 'Epson L3110',
            'type' => 'pdf',
            'status' => 'failed',
            'approval_status' => 'auto_approved',
            'file_path' => 'test.pdf',
            'retry_count' => 1,
        ]);

        // Put a fake file in storage
        \Illuminate\Support\Facades\Storage::fake('private');
        \Illuminate\Support\Facades\Storage::put('test.pdf', 'fake content');

        // Login an admin user to access the web controller
        $admin = \App\Models\User::create([
            'name'     => 'Admin User',
            'email'    => 'admin@example.com',
            'password' => bcrypt('password'),
            'role'     => 'super-admin',
        ]);

        $response = $this->actingAs($admin)
            ->post("/jobs/{$job->id}/retry");

        $response->assertRedirect();
        
        // Assert new job was created with status 'pending' (not 'queued') and retried_from_job_id set
        $this->assertDatabaseHas('print_jobs', [
            'retried_from_job_id' => $job->id,
            'retry_count' => 2,
            'status' => 'pending',
        ]);
    }

    public function test_profile_printer_pool_routing()
    {
        $rawKey = Str::random(32);

        $company = \App\Models\Company::create(['name' => 'Corp', 'code' => 'CO', 'is_active' => true]);
        $branch = \App\Models\Branch::create(['company_id' => $company->id, 'name' => 'Branch', 'code' => 'BR', 'is_active' => true]);

        $agent = PrintAgent::create([
            'name' => 'Test Agent',
            'agent_key' => hash('sha256', $rawKey),
            'ip_address' => '127.0.0.1',
            'is_active' => true,
            'branch_id' => $branch->id,
            'printers' => ['Printer A'],
        ]);

        $pool = \App\Models\PrinterPool::create([
            'name' => 'Branch Pool',
            'strategy' => 'round_robin',
            'active' => true,
        ]);

        \App\Models\PrinterPoolPrinter::create([
            'pool_id' => $pool->id,
            'printer_name' => 'Printer A',
            'priority' => 1,
            'active' => true,
        ]);

        $profile = \App\Models\PrintProfile::create([
            'name' => 'Pool Profile',
            'branch_id' => $branch->id,
            'pool_id' => $pool->id,
            'print_agent_id' => null,
            'paper_size' => 'A4',
            'orientation' => 'portrait',
            'copies' => 1,
            'duplex' => 'none',
        ]);

        // 1. Get profiles API
        $response = $this->withHeader('X-Agent-Key', $rawKey)
            ->getJson('/api/print-hub/profiles');

        $response->assertStatus(200);
        $profiles = $response->json('data.profiles');
        $this->assertCount(1, $profiles);
        $this->assertEquals($pool->id, $profiles[0]['pool_id']);

        // 2. Create job with pool_id and null printer_name
        $job = PrintJob::create([
            'job_id' => Str::uuid(),
            'print_agent_id' => $agent->id,
            'branch_id' => $branch->id,
            'pool_id' => $pool->id,
            'printer_name' => null,
            'type' => 'pdf',
            'status' => 'pending',
            'approval_status' => 'auto_approved',
            'file_path' => 'test.pdf',
        ]);

        // Put a fake file in storage
        \Illuminate\Support\Facades\Storage::fake('local');
        \Illuminate\Support\Facades\Storage::put('test.pdf', 'content');

        // 3. Pull queue API - printer should be resolved dynamically
        $response2 = $this->withHeader('X-Agent-Key', $rawKey)
            ->getJson('/api/print-hub/queue');

        $response2->assertStatus(200);
        $jobs = $response2->json('data.jobs');
        $this->assertCount(1, $jobs);
        $this->assertEquals('Printer A', $jobs[0]['printer']);

        // Assert database record was updated
        $job->refresh();
        $this->assertEquals('Printer A', $job->printer_name);
    }
}
