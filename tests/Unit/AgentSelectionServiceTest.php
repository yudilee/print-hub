<?php

namespace Tests\Unit;

use App\Models\PrintAgent;
use App\Models\PrintProfile;
use App\Services\AgentSelectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentSelectionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected PrintAgent $onlineAgent;
    protected PrintAgent $offlineAgent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->onlineAgent = PrintAgent::create([
            'name'         => 'Online Agent',
            'agent_key'    => 'online-key',
            'is_active'    => true,
            'last_seen_at' => now(),
            'branch_id'    => null,
        ]);

        $this->offlineAgent = PrintAgent::create([
            'name'         => 'Offline Agent',
            'agent_key'    => 'offline-key',
            'is_active'    => true,
            'last_seen_at' => now()->subMinutes(10),
            'branch_id'    => null,
        ]);
    }

    public function test_select_returns_explicit_agent_by_id()
    {
        $agent = AgentSelectionService::select(
            $this->onlineAgent->id,
            null,
            null,
            null
        );
        $this->assertSame($this->onlineAgent->id, $agent->id);
    }

    public function test_select_returns_pinned_agent_when_online()
    {
        $profile = new PrintProfile([
            'print_agent_id' => $this->onlineAgent->id,
        ]);
        $profile->setRelation('agent', $this->onlineAgent);

        $agent = AgentSelectionService::select(null, $profile, null, 'test-queue');
        $this->assertSame($this->onlineAgent->id, $agent->id);
    }

    public function test_select_throws_when_pinned_agent_is_offline()
    {
        $profile = new PrintProfile([
            'name'           => 'test-queue',
            'print_agent_id' => $this->offlineAgent->id,
        ]);
        $profile->setRelation('agent', $this->offlineAgent);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('offline');

        AgentSelectionService::select(null, $profile, null, 'test-queue');
    }

    public function test_select_falls_back_to_any_online_agent()
    {
        $agent = AgentSelectionService::select(null, null, null, null);
        $this->assertNotNull($agent);
        $this->assertTrue($agent->isOnline());
    }

    public function test_select_throws_when_no_agents_exist()
    {
        PrintAgent::query()->delete();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No online agent');

        AgentSelectionService::select(null, null, null, null);
    }
}
