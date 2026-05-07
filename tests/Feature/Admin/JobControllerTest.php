<?php

namespace Tests\Feature\Admin;

use App\Models\PrintAgent;
use App\Models\PrintJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class JobControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected PrintAgent $agent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name'     => 'Admin User',
            'email'    => 'admin@example.com',
            'password' => bcrypt('password'),
            'role'     => 'super-admin',
        ]);

        $this->agent = PrintAgent::create([
            'name'       => 'Test Agent',
            'agent_key'  => PrintAgent::hashKey(Str::random(32)),
            'ip_address' => '127.0.0.1',
            'is_active'  => true,
        ]);
    }

    public function test_unauthenticated_access_is_redirected()
    {
        $response = $this->get(route('admin.jobs'));
        $response->assertRedirect(route('login'));
    }

    public function test_index_returns_jobs_view()
    {
        $job = PrintJob::create([
            'job_id'         => (string) Str::uuid(),
            'print_agent_id' => $this->agent->id,
            'printer_name'   => 'Epson L3110',
            'type'           => 'pdf',
            'status'         => 'pending',
            'file_path'      => 'print_jobs/test.pdf',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.jobs'));

        $response->assertOk();
        $response->assertViewIs('admin.jobs');
        $response->assertSee($job->job_id);
    }

    public function test_index_filters_by_status()
    {
        PrintJob::create([
            'job_id'         => (string) Str::uuid(),
            'print_agent_id' => $this->agent->id,
            'printer_name'   => 'Printer A',
            'type'           => 'pdf',
            'status'         => 'pending',
            'file_path'      => 'print_jobs/a.pdf',
        ]);
        PrintJob::create([
            'job_id'         => (string) Str::uuid(),
            'print_agent_id' => $this->agent->id,
            'printer_name'   => 'Printer B',
            'type'           => 'pdf',
            'status'         => 'success',
            'file_path'      => 'print_jobs/b.pdf',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.jobs', ['status' => 'success']));

        $response->assertOk();
        $response->assertSee('success');
    }

    public function test_index_filters_by_agent()
    {
        $agent2 = PrintAgent::create([
            'name'       => 'Agent Two',
            'agent_key'  => PrintAgent::hashKey(Str::random(32)),
            'ip_address' => '127.0.0.2',
            'is_active'  => true,
        ]);

        $job1 = PrintJob::create([
            'job_id'         => (string) Str::uuid(),
            'print_agent_id' => $this->agent->id,
            'printer_name'   => 'Printer A',
            'type'           => 'pdf',
            'status'         => 'pending',
            'file_path'      => 'print_jobs/a.pdf',
        ]);
        $job2 = PrintJob::create([
            'job_id'         => (string) Str::uuid(),
            'print_agent_id' => $agent2->id,
            'printer_name'   => 'Printer B',
            'type'           => 'pdf',
            'status'         => 'pending',
            'file_path'      => 'print_jobs/b.pdf',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.jobs', ['agent_id' => $agent2->id]));

        $response->assertOk();
    }

    public function test_update_status_changes_job_status()
    {
        $job = PrintJob::create([
            'job_id'         => (string) Str::uuid(),
            'print_agent_id' => $this->agent->id,
            'printer_name'   => 'Epson L3110',
            'type'           => 'pdf',
            'status'         => 'pending',
            'file_path'      => 'print_jobs/test.pdf',
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.jobs.status', $job), [
                'status' => 'success',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('print_jobs', [
            'id'     => $job->id,
            'status' => 'success',
        ]);
    }

    public function test_update_status_validates_status_value()
    {
        $job = PrintJob::create([
            'job_id'         => (string) Str::uuid(),
            'print_agent_id' => $this->agent->id,
            'printer_name'   => 'Epson L3110',
            'type'           => 'pdf',
            'status'         => 'pending',
            'file_path'      => 'print_jobs/test.pdf',
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.jobs.status', $job), [
                'status' => 'invalid-status',
            ]);

        $response->assertSessionHasErrors(['status']);
    }

    public function test_retry_creates_new_job_copy()
    {
        $job = PrintJob::create([
            'job_id'         => (string) Str::uuid(),
            'print_agent_id' => $this->agent->id,
            'printer_name'   => 'Epson L3110',
            'type'           => 'pdf',
            'status'         => 'failed',
            'file_path'      => 'print_jobs/test.pdf',
            'error'          => 'Out of paper',
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.jobs.retry', $job));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Original job should still exist
        $this->assertDatabaseHas('print_jobs', ['id' => $job->id, 'status' => 'failed']);

        // A new job should have been created
        $this->assertDatabaseHas('print_jobs', [
            'print_agent_id' => $this->agent->id,
            'printer_name'   => 'Epson L3110',
            'status'         => 'pending',
        ]);
    }

    public function test_download_returns_404_for_missing_file()
    {
        $job = PrintJob::create([
            'job_id'         => (string) Str::uuid(),
            'print_agent_id' => $this->agent->id,
            'printer_name'   => 'Epson L3110',
            'type'           => 'pdf',
            'status'         => 'pending',
            'file_path'      => 'print_jobs/nonexistent.pdf',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.jobs.download', $job));

        $response->assertStatus(404);
    }

    public function test_authenticated_user_can_access_jobs()
    {
        $user = User::create([
            'name'     => 'Regular User',
            'email'    => 'user@example.com',
            'password' => bcrypt('password'),
            'role'     => 'user',
        ]);

        $response = $this->actingAs($user)
            ->get(route('admin.jobs'));

        // Admin routes only have 'auth' and 'session.activity' middleware, no role check
        $response->assertOk();
    }
}
