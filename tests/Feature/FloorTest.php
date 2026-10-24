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

        $this->getJson('/api/floors')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Lantai P1')
            ->assertJsonPath('data.0.total', 4)
            ->assertJsonPath('data.0.available', 2)
            ->assertJsonPath('data.0.occupied', 1)
            ->assertJsonPath('data.0.unknown', 1);
    }

    public function test_floor_detail_api_lists_slots_with_status(): void
    {
        $floor = Floor::factory()->create();
        Slot::factory()->occupied()->for($floor)->create(['code' => 'P1-A01']);

        $this->getJson("/api/floors/{$floor->id}")
            ->assertOk()
            ->assertJsonPath('data.slots.0.code', 'P1-A01')
            ->assertJsonPath('data.slots.0.status', 'occupied');
    }

    public function test_web_pages_render_floor_then_slots(): void
    {
        $floor = Floor::factory()->create(['name' => 'Lantai P2', 'level' => 2]);
        Slot::factory()->available()->for($floor)->create(['code' => 'P2-B07']);

        $this->get('/')->assertOk()->assertSee('Lantai P2')->assertSee(route('floors.show', $floor));
        $this->get(route('floors.show', $floor))->assertOk()->assertSee('P2-B07')->assertSee('s-available');
    }

    public function test_simulator_reports_every_sensor(): void
    {
        $this->seed();

        $this->artisan('parkir:simulate', ['--once' => true])->assertSuccessful();

        $this->assertSame(120, Slot::count());
        $this->assertSame(0, Slot::whereNull('last_seen_at')->count());
    }
}
