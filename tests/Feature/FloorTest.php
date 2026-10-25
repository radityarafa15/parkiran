<?php

namespace Tests\Feature;

use App\Models\Floor;
use App\Models\Slot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FloorTest extends TestCase
{
    use RefreshDatabase;

    public function test_floor_list_api_returns_counts_per_floor(): void
    {
        $floor = Floor::factory()->create(['name' => 'Lantai P1', 'level' => 1]);
        Slot::factory()->count(2)->available()->for($floor)->create();
        Slot::factory()->occupied()->for($floor)->create();
        Slot::factory()->for($floor)->create();
        Slot::factory()->available()->for($floor)->create(['last_seen_at' => now()->subHour()]);

        $this->getJson('/api/floors')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Lantai P1')
            ->assertJsonPath('data.0.total', 5)
            ->assertJsonPath('data.0.available', 2)
            ->assertJsonPath('data.0.occupied', 1)
            ->assertJsonPath('data.0.unknown', 2);
    }

    public function test_floor_detail_api_lists_zone_counts(): void
    {
        $floor = Floor::factory()->create();
        Slot::factory()->available()->for($floor)->create(['zone' => 'A']);
        Slot::factory()->occupied()->for($floor)->create(['zone' => 'B']);
        Slot::factory()->available()->for($floor)->create(['zone' => 'B']);

        $this->getJson("/api/floors/{$floor->id}")
            ->assertOk()
            ->assertJsonPath('data.total', 3)
            ->assertJsonPath('data.zones', [
                ['zone' => 'A', 'total' => 1, 'available' => 1, 'occupied' => 0, 'unknown' => 0],
                ['zone' => 'B', 'total' => 2, 'available' => 1, 'occupied' => 1, 'unknown' => 0],
            ]);
    }

    public function test_slots_api_filters_by_zone(): void
    {
        $floor = Floor::factory()->create();
        Slot::factory()->occupied()->for($floor)->create(['code' => 'P1-A001', 'zone' => 'A']);
        Slot::factory()->available()->for($floor)->create(['code' => 'P1-B001', 'zone' => 'B']);

        $this->getJson("/api/floors/{$floor->id}/slots?zone=B")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'P1-B001')
            ->assertJsonPath('data.0.status', 'available');
    }

    public function test_realtime_config_is_public(): void
    {
        config(['parkir.realtime' => ['key' => 'abc', 'host' => 'parkir.test', 'port' => 443, 'scheme' => 'https']]);

        $this->getJson('/api/realtime')->assertOk()->assertJsonPath('data.host', 'parkir.test');
    }

    public function test_web_pages_render_floor_then_slots(): void
    {
        $floor = Floor::factory()->create(['name' => 'Lantai P2', 'level' => 2]);
        Slot::factory()->available()->for($floor)->create(['code' => 'P2-B007', 'zone' => 'B']);

        $this->get('/')->assertOk()->assertSee('Lantai P2')->assertSee(route('floors.show', $floor));
        $this->get(route('floors.show', $floor))->assertOk()->assertSee('Zona B')->assertSee('B007')->assertSee('s-available');
    }

    public function test_seeder_creates_6995_slots_and_simulator_reports_every_sensor(): void
    {
        $this->seed();

        $this->assertSame(3, Floor::count());
        $this->assertSame(6995, Slot::count());
        $this->assertTrue(Slot::where('code', 'P1-A001')->exists());

        $this->artisan('parkir:simulate', ['--once' => true])->assertSuccessful();

        $this->assertSame(0, Slot::whereNull('last_seen_at')->count());
    }
}
