<?php

namespace Tests\Feature;

use App\Models\PrinterPool;
use App\Models\PrinterPoolPrinter;
use App\Models\PrintAgent;
use App\Models\User;
use App\Services\PrintJobOrchestrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PrinterPoolTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected PrintAgent $agent1;
    protected PrintAgent $agent2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name'     => 'Admin User',
            'email'    => 'admin@example.com',
            'password' => bcrypt('password'),
            'role'     => 'super-admin',
        ]);

        $this->agent1 = PrintAgent::create([
            'name'         => 'Agent One',
            'agent_key'    => PrintAgent::hashKey(Str::random(32)),
            'is_active'    => true,
            'ip_address'   => '127.0.0.1',
            'last_seen_at' => now(),
            'printers'     => ['Printer A', 'Printer B'],
        ]);

        $this->agent2 = PrintAgent::create([
            'name'         => 'Agent Two',
            'agent_key'    => PrintAgent::hashKey(Str::random(32)),
            'is_active'    => true,
            'ip_address'   => '127.0.0.2',
            'last_seen_at' => now(),
            'printers'     => ['Printer C', 'Printer D'],
        ]);
    }

    public function test_pool_creation_with_multiple_agents()
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.pools.store'), [
                'name'        => 'Test Pool',
                'description' => 'A pool of printers',
                'strategy'    => 'round_robin',
                'active'      => true,
                'printers'    => [
                    ['name' => 'Printer A', 'priority' => 1],
                    ['name' => 'Printer C', 'priority' => 2],
                ],
            ]);

        $response->assertRedirect(route('admin.pools'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('printer_pools', [
            'name'     => 'Test Pool',
            'strategy' => 'round_robin',
            'active'   => true,
        ]);

        $this->assertDatabaseHas('printer_pool_printers', [
            'printer_name' => 'Printer A',
            'priority'     => 1,
        ]);
        $this->assertDatabaseHas('printer_pool_printers', [
            'printer_name' => 'Printer C',
            'priority'     => 2,
        ]);
    }

    public function test_pool_index_shows_pools()
    {
        $pool = PrinterPool::create([
            'name'     => 'My Pool',
            'strategy' => 'round_robin',
            'active'   => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.pools'));

        $response->assertOk();
        $response->assertViewIs('admin.pools.index');
        $response->assertSee($pool->name);
    }

    public function test_pool_update_modifies_pool()
    {
        $pool = PrinterPool::create([
            'name'     => 'Original Pool',
            'strategy' => 'round_robin',
            'active'   => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->put(route('admin.pools.update', $pool), [
                'name'        => 'Updated Pool',
                'description' => 'Updated description',
                'strategy'    => 'random',
                'active'      => true,
                'printers'    => [
                    ['name' => 'Printer B', 'priority' => 1],
                ],
            ]);

        $response->assertRedirect(route('admin.pools'));
        $this->assertDatabaseHas('printer_pools', [
            'id'       => $pool->id,
            'name'     => 'Updated Pool',
            'strategy' => 'random',
        ]);
    }

    public function test_pool_destroy_removes_pool()
    {
        $pool = PrinterPool::create([
            'name'     => 'Delete Pool',
            'strategy' => 'round_robin',
            'active'   => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.pools.destroy', $pool));

        $response->assertRedirect(route('admin.pools'));
        $this->assertDatabaseMissing('printer_pools', ['id' => $pool->id]);
    }

    public function test_round_robin_selection_cycles_through_printers()
    {
        $pool = PrinterPool::create([
            'name'     => 'Round Robin Pool',
            'strategy' => 'round_robin',
            'active'   => true,
        ]);

        PrinterPoolPrinter::create(['pool_id' => $pool->id, 'printer_name' => 'Printer A', 'priority' => 1, 'active' => true]);
        PrinterPoolPrinter::create(['pool_id' => $pool->id, 'printer_name' => 'Printer B', 'priority' => 2, 'active' => true]);

        $orchestrator = app(PrintJobOrchestrator::class);

        // First selection should be Printer A (index 0)
        $first = $orchestrator->selectPrinterFromPool($pool->id);
        $this->assertEquals('Printer A', $first);

        // Second selection should be Printer B (index 1)
        $second = $orchestrator->selectPrinterFromPool($pool->id);
        $this->assertEquals('Printer B', $second);

        // Third selection cycles back to Printer A (index 0)
        $third = $orchestrator->selectPrinterFromPool($pool->id);
        $this->assertEquals('Printer A', $third);
    }

    public function test_failover_strategy_returns_highest_priority_printer()
    {
        $pool = PrinterPool::create([
            'name'     => 'Failover Pool',
            'strategy' => 'failover',
            'active'   => true,
        ]);

        PrinterPoolPrinter::create(['pool_id' => $pool->id, 'printer_name' => 'Primary Printer', 'priority' => 1, 'active' => true]);
        PrinterPoolPrinter::create(['pool_id' => $pool->id, 'printer_name' => 'Backup Printer', 'priority' => 2, 'active' => true]);

        $orchestrator = app(PrintJobOrchestrator::class);

        $selected = $orchestrator->selectPrinterFromPool($pool->id);
        $this->assertEquals('Primary Printer', $selected);
    }

    public function test_inactive_pool_throws_exception()
    {
        $pool = PrinterPool::create([
            'name'     => 'Inactive Pool',
            'strategy' => 'round_robin',
            'active'   => false,
        ]);

        PrinterPoolPrinter::create(['pool_id' => $pool->id, 'printer_name' => 'Printer A', 'priority' => 1, 'active' => true]);

        $orchestrator = app(PrintJobOrchestrator::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Printer pool 'Inactive Pool' is inactive.");

        $orchestrator->selectPrinterFromPool($pool->id);
    }

    public function test_pool_with_no_active_printers_throws_exception()
    {
        $pool = PrinterPool::create([
            'name'     => 'Empty Pool',
            'strategy' => 'round_robin',
            'active'   => true,
        ]);

        $orchestrator = app(PrintJobOrchestrator::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("No active printers in pool 'Empty Pool'.");

        $orchestrator->selectPrinterFromPool($pool->id);
    }

    public function test_pool_creation_validates_required_fields()
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.pools.store'), []);

        $response->assertSessionHasErrors(['name', 'strategy']);
    }

    public function test_pool_creation_validates_strategy_value()
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.pools.store'), [
                'name'     => 'Bad Pool',
                'strategy' => 'invalid_strategy',
            ]);

        $response->assertSessionHasErrors(['strategy']);
    }

    public function test_random_strategy_selects_a_printer()
    {
        $pool = PrinterPool::create([
            'name'     => 'Random Pool',
            'strategy' => 'random',
            'active'   => true,
        ]);

        PrinterPoolPrinter::create(['pool_id' => $pool->id, 'printer_name' => 'Printer A', 'priority' => 1, 'active' => true]);
        PrinterPoolPrinter::create(['pool_id' => $pool->id, 'printer_name' => 'Printer B', 'priority' => 2, 'active' => true]);

        $orchestrator = app(PrintJobOrchestrator::class);

        $selected = $orchestrator->selectPrinterFromPool($pool->id);
        $this->assertContains($selected, ['Printer A', 'Printer B']);
    }
}
