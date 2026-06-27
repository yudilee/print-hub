<?php

namespace Tests\Feature\Admin;

use App\Models\Branch;
use App\Models\Company;
use App\Models\PrintAgent;
use App\Models\PrintProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected PrintAgent $agent;
    protected Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name'     => 'Admin User',
            'email'    => 'admin@example.com',
            'password' => bcrypt('password'),
            'role'     => 'super-admin',
        ]);

        $company = Company::create(['name' => 'Test Corp', 'code' => 'TC', 'is_active' => true]);

        $this->branch = Branch::create([
            'company_id' => $company->id,
            'name'       => 'Main Branch',
            'code'       => 'MB',
            'is_active'  => true,
        ]);

        $this->agent = PrintAgent::create([
            'name'       => 'Test Agent',
            'agent_key'  => PrintAgent::hashKey(Str::random(32)),
            'ip_address' => '127.0.0.1',
            'is_active'  => true,
            'branch_id'  => $this->branch->id,
        ]);
    }

    public function test_unauthenticated_access_is_redirected()
    {
        $response = $this->get(route('admin.profiles'));
        $response->assertRedirect(route('login'));
    }

    public function test_index_returns_profiles_view()
    {
        $profile = PrintProfile::create([
            'name'           => 'Test Profile',
            'branch_id'      => $this->branch->id,
            'print_agent_id' => $this->agent->id,
            'paper_size'     => 'A4',
            'orientation'    => 'portrait',
            'copies'         => 1,
            'duplex'         => 'none',
            'default_printer' => 'Epson L3110',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.profiles'));

        $response->assertOk();
        $response->assertViewIs('admin.profiles');
        $response->assertSee($profile->name);
    }

    public function test_store_creates_profile_and_redirects()
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.profiles.store'), [
                'name'            => 'New Profile',
                'description'     => 'Test description',
                'branch_id'       => $this->branch->id,
                'paper_size'      => 'A4',
                'orientation'     => 'portrait',
                'copies'          => 2,
                'duplex'          => 'none',
                'print_agent_id'  => $this->agent->id,
                'default_printer' => 'Epson L3110',
            ]);

        $response->assertRedirect(route('admin.profiles'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('print_profiles', [
            'name'       => 'New Profile',
            'paper_size' => 'A4',
            'copies'     => 2,
        ]);
    }

    public function test_store_validates_required_fields()
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.profiles.store'), []);

        $response->assertSessionHasErrors(['name', 'branch_id', 'paper_size', 'orientation', 'copies', 'duplex', 'default_printer']);
    }

    public function test_store_creates_profile_with_printer_pool()
    {
        $pool = \App\Models\PrinterPool::create([
            'name'     => 'My Pool',
            'strategy' => 'round_robin',
            'active'   => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.profiles.store'), [
                'name'            => 'Pool Profile',
                'description'     => 'Using a pool',
                'branch_id'       => $this->branch->id,
                'paper_size'      => 'A4',
                'orientation'     => 'portrait',
                'copies'          => 1,
                'duplex'          => 'none',
                'pool_id'         => $pool->id,
            ]);

        $response->assertRedirect(route('admin.profiles'));
        $this->assertDatabaseHas('print_profiles', [
            'name'    => 'Pool Profile',
            'pool_id' => $pool->id,
            'default_printer' => null,
            'print_agent_id'  => null,
        ]);
    }

    public function test_store_validates_unique_name()
    {
        PrintProfile::create([
            'name'           => 'Duplicate Profile',
            'branch_id'      => $this->branch->id,
            'print_agent_id' => $this->agent->id,
            'paper_size'     => 'A4',
            'orientation'    => 'portrait',
            'copies'         => 1,
            'duplex'         => 'none',
            'default_printer' => 'Epson L3110',
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.profiles.store'), [
                'name'            => 'Duplicate Profile',
                'branch_id'       => $this->branch->id,
                'paper_size'      => 'A4',
                'orientation'     => 'portrait',
                'copies'          => 1,
                'duplex'          => 'none',
                'print_agent_id'  => $this->agent->id,
                'default_printer' => 'Epson L3110',
            ]);

        $response->assertSessionHasErrors(['name']);
    }

    public function test_update_modifies_profile()
    {
        $profile = PrintProfile::create([
            'name'           => 'Original Profile',
            'branch_id'      => $this->branch->id,
            'print_agent_id' => $this->agent->id,
            'paper_size'     => 'A4',
            'orientation'    => 'portrait',
            'copies'         => 1,
            'duplex'         => 'none',
            'default_printer' => 'Epson L3110',
        ]);

        $response = $this->actingAs($this->admin)
            ->put(route('admin.profiles.update', $profile), [
                'name'            => 'Updated Profile',
                'branch_id'       => $this->branch->id,
                'paper_size'      => 'Letter',
                'orientation'     => 'landscape',
                'copies'          => 3,
                'duplex'          => 'long',
                'print_agent_id'  => $this->agent->id,
                'default_printer' => 'HP LaserJet',
            ]);

        $response->assertRedirect(route('admin.profiles'));
        $this->assertDatabaseHas('print_profiles', [
            'id'             => $profile->id,
            'name'           => 'Updated Profile',
            'paper_size'     => 'Letter',
            'orientation'    => 'landscape',
            'copies'         => 3,
            'default_printer' => 'HP LaserJet',
        ]);
    }

    public function test_destroy_removes_profile()
    {
        $profile = PrintProfile::create([
            'name'           => 'Delete Me',
            'branch_id'      => $this->branch->id,
            'print_agent_id' => $this->agent->id,
            'paper_size'     => 'A4',
            'orientation'    => 'portrait',
            'copies'         => 1,
            'duplex'         => 'none',
            'default_printer' => 'Epson L3110',
        ]);

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.profiles.destroy', $profile));

        $response->assertRedirect(route('admin.profiles'));
        $this->assertDatabaseMissing('print_profiles', ['id' => $profile->id]);
    }

    public function test_authenticated_user_can_access_profiles()
    {
        $user = User::create([
            'name'     => 'Regular User',
            'email'    => 'user@example.com',
            'password' => bcrypt('password'),
            'role'     => 'user',
        ]);

        $response = $this->actingAs($user)
            ->get(route('admin.profiles'));

        // Admin routes only have 'auth' and 'session.activity' middleware, no role check
        $response->assertOk();
    }

    public function test_index_shows_multiple_profiles()
    {
        PrintProfile::create([
            'name'           => 'Profile A',
            'branch_id'      => $this->branch->id,
            'print_agent_id' => $this->agent->id,
            'paper_size'     => 'A4',
            'orientation'    => 'portrait',
            'copies'         => 1,
            'duplex'         => 'none',
            'default_printer' => 'Printer A',
        ]);
        PrintProfile::create([
            'name'           => 'Profile B',
            'branch_id'      => $this->branch->id,
            'print_agent_id' => $this->agent->id,
            'paper_size'     => 'Letter',
            'orientation'    => 'landscape',
            'copies'         => 2,
            'duplex'         => 'short',
            'default_printer' => 'Printer B',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.profiles'));

        $response->assertOk();
        $response->assertSee('Profile A');
        $response->assertSee('Profile B');
    }
}
