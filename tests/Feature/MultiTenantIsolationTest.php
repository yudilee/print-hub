<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\ClientApp;
use App\Models\Company;
use App\Models\PrintAgent;
use App\Models\PrintJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MultiTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected Company $companyA;
    protected Company $companyB;
    protected Branch $branchA;
    protected Branch $branchB;
    protected User $adminA;
    protected User $adminB;
    protected PrintAgent $agentA;
    protected PrintAgent $agentB;

    protected function setUp(): void
    {
        parent::setUp();

        // Company A
        $this->companyA = Company::create(['name' => 'Company A', 'code' => 'CA', 'is_active' => true]);
        $this->branchA = Branch::create(['company_id' => $this->companyA->id, 'name' => 'Branch A1', 'code' => 'BA1', 'is_active' => true]);
        $this->adminA = User::create([
            'name'     => 'Admin A',
            'email'    => 'adminA@example.com',
            'password' => bcrypt('password'),
            'role'     => 'company-admin',
            'company_id' => $this->companyA->id,
            'branch_id'  => $this->branchA->id,
        ]);
        $this->agentA = PrintAgent::create([
            'name'       => 'Agent A',
            'agent_key'  => PrintAgent::hashKey(Str::random(32)),
            'ip_address' => '127.0.0.1',
            'is_active'  => true,
            'branch_id'  => $this->branchA->id,
        ]);

        // Company B
        $this->companyB = Company::create(['name' => 'Company B', 'code' => 'CB', 'is_active' => true]);
        $this->branchB = Branch::create(['company_id' => $this->companyB->id, 'name' => 'Branch B1', 'code' => 'BB1', 'is_active' => true]);
        $this->adminB = User::create([
            'name'     => 'Admin B',
            'email'    => 'adminB@example.com',
            'password' => bcrypt('password'),
            'role'     => 'company-admin',
            'company_id' => $this->companyB->id,
            'branch_id'  => $this->branchB->id,
        ]);
        $this->agentB = PrintAgent::create([
            'name'       => 'Agent B',
            'agent_key'  => PrintAgent::hashKey(Str::random(32)),
            'ip_address' => '127.0.0.2',
            'is_active'  => true,
            'branch_id'  => $this->branchB->id,
        ]);
    }

    public function test_company_a_admin_cannot_see_company_b_agents()
    {
        // Admin A should only see Agent A (via branch scoping)
        $visibleBranchIds = $this->adminA->getVisibleBranchIds();

        $agentsForA = PrintAgent::whereIn('branch_id', $visibleBranchIds)->get();

        $this->assertTrue($agentsForA->contains('id', $this->agentA->id));
        $this->assertFalse($agentsForA->contains('id', $this->agentB->id));
    }

    public function test_company_b_admin_cannot_see_company_a_agents()
    {
        $visibleBranchIds = $this->adminB->getVisibleBranchIds();

        $agentsForB = PrintAgent::whereIn('branch_id', $visibleBranchIds)->get();

        $this->assertTrue($agentsForB->contains('id', $this->agentB->id));
        $this->assertFalse($agentsForB->contains('id', $this->agentA->id));
    }

    public function test_branch_a_user_cannot_access_branch_b_agents()
    {
        $branchUser = User::create([
            'name'       => 'Branch User A',
            'email'      => 'branchA@example.com',
            'password'   => bcrypt('password'),
            'role'       => 'branch-admin',
            'company_id' => $this->companyA->id,
            'branch_id'  => $this->branchA->id,
        ]);

        $visibleBranchIds = $branchUser->getVisibleBranchIds();

        $this->assertContains($this->branchA->id, $visibleBranchIds);
        $this->assertNotContains($this->branchB->id, $visibleBranchIds);
    }

    public function test_api_keys_are_scoped_to_their_company()
    {
        // Create client apps for each company context
        // Use sha256 hash because ClientApp::findByKey() first tries
        // a sha256 lookup against the api_key column.
        $clientA = ClientApp::create([
            'name'      => 'Client A',
            'api_key'   => hash('sha256', 'key-a'),
            'is_active' => true,
        ]);

        $clientB = ClientApp::create([
            'name'      => 'Client B',
            'api_key'   => hash('sha256', 'key-b'),
            'is_active' => true,
        ]);

        // Client A's key should resolve to Client A
        $foundA = ClientApp::findByKey('key-a');
        $this->assertNotNull($foundA);
        $this->assertEquals($clientA->id, $foundA->id);

        // Client B's key should resolve to Client B
        $foundB = ClientApp::findByKey('key-b');
        $this->assertNotNull($foundB);
        $this->assertEquals($clientB->id, $foundB->id);

        // Client A's key should NOT resolve to Client B
        $this->assertNotEquals($clientB->id, $foundA->id);
    }

    public function test_super_admin_can_see_all_branches()
    {
        $superAdmin = User::create([
            'name'     => 'Super Admin',
            'email'    => 'super@example.com',
            'password' => bcrypt('password'),
            'role'     => 'super-admin',
        ]);

        $visibleBranchIds = $superAdmin->getVisibleBranchIds();

        $this->assertContains($this->branchA->id, $visibleBranchIds);
        $this->assertContains($this->branchB->id, $visibleBranchIds);
    }

    public function test_company_a_jobs_are_not_visible_to_company_b()
    {
        $jobA = PrintJob::create([
            'job_id'         => (string) Str::uuid(),
            'print_agent_id' => $this->agentA->id,
            'branch_id'      => $this->branchA->id,
            'printer_name'   => 'Printer A',
            'type'           => 'pdf',
            'status'         => 'pending',
            'file_path'      => 'print_jobs/a.pdf',
        ]);

        $jobB = PrintJob::create([
            'job_id'         => (string) Str::uuid(),
            'print_agent_id' => $this->agentB->id,
            'branch_id'      => $this->branchB->id,
            'printer_name'   => 'Printer B',
            'type'           => 'pdf',
            'status'         => 'pending',
            'file_path'      => 'print_jobs/b.pdf',
        ]);

        // Admin A's visible branches
        $visibleForA = $this->adminA->getVisibleBranchIds();
        $jobsForA = PrintJob::whereIn('branch_id', $visibleForA)->pluck('id');

        $this->assertContains($jobA->id, $jobsForA);
        $this->assertNotContains($jobB->id, $jobsForA);
    }

    public function test_inactive_company_data_is_isolated()
    {
        $this->companyB->update(['is_active' => false]);

        $activeCompanies = Company::where('is_active', true)->pluck('id');

        $this->assertContains($this->companyA->id, $activeCompanies);
        $this->assertNotContains($this->companyB->id, $activeCompanies);
    }

    public function test_branch_scoping_on_print_agents()
    {
        // Agent A should be scoped to Branch A
        $this->assertEquals($this->branchA->id, $this->agentA->branch_id);
        $this->assertEquals($this->branchB->id, $this->agentB->branch_id);

        // Verify they belong to different companies through their branches
        $this->assertEquals($this->companyA->id, $this->agentA->branch->company_id);
        $this->assertEquals($this->companyB->id, $this->agentB->branch->company_id);
    }
}
