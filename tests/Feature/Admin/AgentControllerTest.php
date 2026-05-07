<?php

namespace Tests\Feature\Admin;

use App\Models\Branch;
use App\Models\Company;
use App\Models\PrintAgent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AgentControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name'     => 'Admin User',
            'email'    => 'admin@example.com',
            'password' => bcrypt('password'),
            'role'     => 'super-admin',
        ]);
    }

    public function test_unauthenticated_access_is_redirected_to_login()
    {
        $response = $this->get(route('admin.agents'));
        $response->assertRedirect(route('login'));
    }

    public function test_index_returns_agents_view()
    {
        $agent = PrintAgent::create([
            'name'       => 'Test Agent',
            'agent_key'  => PrintAgent::hashKey(Str::random(32)),
            'ip_address' => '127.0.0.1',
            'is_active'  => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.agents'));

        $response->assertOk();
        $response->assertViewIs('admin.agents');
        $response->assertSee($agent->name);
    }

    public function test_store_creates_agent_and_redirects()
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.agents.store'), [
                'name'       => 'New Agent',
                'branch_id'  => null,
                'location'   => 'Floor 1',
                'department' => 'IT',
            ]);

        $response->assertRedirect(route('admin.agents'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('print_agents', [
            'name'       => 'New Agent',
            'location'   => 'Floor 1',
            'department' => 'IT',
        ]);
    }

    public function test_store_validates_required_fields()
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.agents.store'), []);

        $response->assertSessionHasErrors(['name']);
    }

    public function test_update_modifies_agent()
    {
        $agent = PrintAgent::create([
            'name'       => 'Old Name',
            'agent_key'  => PrintAgent::hashKey(Str::random(32)),
            'ip_address' => '127.0.0.1',
            'is_active'  => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->put(route('admin.agents.update', $agent), [
                'name'       => 'Updated Name',
                'branch_id'  => null,
                'location'   => 'Floor 2',
                'department' => 'HR',
                'is_active'  => true,
            ]);

        $response->assertRedirect(route('admin.agents'));
        $this->assertDatabaseHas('print_agents', [
            'id'         => $agent->id,
            'name'       => 'Updated Name',
            'location'   => 'Floor 2',
            'department' => 'HR',
        ]);
    }

    public function test_regenerate_key_rotates_agent_key()
    {
        $agent = PrintAgent::create([
            'name'       => 'Key Agent',
            'agent_key'  => PrintAgent::hashKey(Str::random(32)),
            'ip_address' => '127.0.0.1',
            'is_active'  => true,
        ]);

        $oldKey = $agent->agent_key;

        $response = $this->actingAs($this->admin)
            ->post(route('admin.agents.regenerate-key', $agent));

        $response->assertRedirect(route('admin.agents'));
        $response->assertSessionHas('success');

        $agent->refresh();
        $this->assertNotNull($agent->last_key_rotated_at);
    }

    public function test_destroy_removes_agent()
    {
        $agent = PrintAgent::create([
            'name'       => 'Delete Me',
            'agent_key'  => PrintAgent::hashKey(Str::random(32)),
            'ip_address' => '127.0.0.1',
            'is_active'  => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.agents.destroy', $agent));

        $response->assertRedirect(route('admin.agents'));
        $this->assertDatabaseMissing('print_agents', ['id' => $agent->id]);
    }

    public function test_authenticated_user_can_access_agents()
    {
        $user = User::create([
            'name'     => 'Regular User',
            'email'    => 'user@example.com',
            'password' => bcrypt('password'),
            'role'     => 'user',
        ]);

        $response = $this->actingAs($user)
            ->get(route('admin.agents'));

        // Admin routes only require auth middleware (no role check on agents)
        $response->assertOk();
    }

    public function test_store_with_branch_creates_agent_with_branch()
    {
        $company = Company::create(['name' => 'Test Corp', 'code' => 'TC', 'is_active' => true]);
        $branch = Branch::create(['company_id' => $company->id, 'name' => 'Main Branch', 'code' => 'MB', 'is_active' => true]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.agents.store'), [
                'name'      => 'Branch Agent',
                'branch_id' => $branch->id,
            ]);

        $response->assertRedirect(route('admin.agents'));
        $this->assertDatabaseHas('print_agents', [
            'name'      => 'Branch Agent',
            'branch_id' => $branch->id,
        ]);
    }

    public function test_index_shows_agent_count()
    {
        PrintAgent::create([
            'name'       => 'Agent A',
            'agent_key'  => PrintAgent::hashKey(Str::random(32)),
            'ip_address' => '127.0.0.1',
            'is_active'  => true,
        ]);
        PrintAgent::create([
            'name'       => 'Agent B',
            'agent_key'  => PrintAgent::hashKey(Str::random(32)),
            'ip_address' => '127.0.0.2',
            'is_active'  => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.agents'));

        $response->assertOk();
        $response->assertSee('Agent A');
        $response->assertSee('Agent B');
    }
}
