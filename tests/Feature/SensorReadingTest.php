<?php

namespace Tests\Feature;

use App\Events\FloorSummaryUpdated;
use App\Events\SlotsChanged;
use App\Models\Slot;
use Illuminate\Broadcasting\Broadcasters\Broadcaster;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class SensorReadingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['parkir.sensor_token' => 'secret', 'parkir.occupied_threshold_cm' => 150]);
    }

    public function test_rejects_requests_without_a_valid_token(): void
    {
        $this->postJson('/api/sensor-readings', ['readings' => []])->assertUnauthorized();
        $this->postJson('/api/sensor-readings', ['readings' => []], ['X-Sensor-Token' => 'wrong'])->assertUnauthorized();
    }

    public function test_short_distance_marks_slot_occupied_and_long_distance_marks_it_available(): void
    {
        Event::fake();

        $a = Slot::factory()->create(['sensor_id' => 'US-A']);
        $b = Slot::factory()->create(['sensor_id' => 'US-B']);

        $this->postJson('/api/sensor-readings', [
            'readings' => [
                ['sensor_id' => 'US-A', 'distance_cm' => 90],
                ['sensor_id' => 'US-B', 'distance_cm' => 250],
                ['sensor_id' => 'US-NOPE', 'distance_cm' => 250],
            ],
        ], ['X-Sensor-Token' => 'secret'])
            ->assertOk()
            ->assertJson(['accepted' => 2, 'changed' => 2, 'unknown_sensors' => ['US-NOPE']]);

        $this->assertSame(Slot::OCCUPIED, $a->fresh()->status());
        $this->assertSame(Slot::AVAILABLE, $b->fresh()->status());
    }

    public function test_broadcasts_only_slots_whose_status_changed(): void
    {
        Event::fake();

        $slot = Slot::factory()->occupied()->create(['sensor_id' => 'US-A']);
        $other = Slot::factory()->occupied()->for($slot->floor)->create(['sensor_id' => 'US-B']);

        $this->postJson('/api/sensor-readings', [
            'readings' => [
                ['sensor_id' => 'US-A', 'distance_cm' => 250],
                ['sensor_id' => 'US-B', 'distance_cm' => 90],
            ],
        ], ['X-Sensor-Token' => 'secret'])->assertJson(['changed' => 1]);

        Event::assertDispatched(SlotsChanged::class, fn (SlotsChanged $e) => $e->floorId === $slot->floor_id
            && $e->slots === [['id' => $slot->id, 'code' => $slot->code, 'zone' => 'A', 'status' => 'available']]);
        Event::assertDispatched(FloorSummaryUpdated::class, fn (FloorSummaryUpdated $e) => $e->summary['available'] === 1
            && $e->summary['occupied'] === 1);
        $this->assertNotNull($other);
    }

    public function test_no_broadcast_when_nothing_changed(): void
    {
        Event::fake();

        Slot::factory()->occupied()->create(['sensor_id' => 'US-A']);

        $this->postJson('/api/sensor-readings', [
            'readings' => [['sensor_id' => 'US-A', 'distance_cm' => 80]],
        ], ['X-Sensor-Token' => 'secret'])->assertJson(['accepted' => 1, 'changed' => 0]);

        Event::assertNotDispatched(SlotsChanged::class);
        Event::assertNotDispatched(FloorSummaryUpdated::class);
    }

    public function test_readings_are_saved_even_when_reverb_is_down(): void
    {
        Broadcast::extend('down', fn () => new class extends Broadcaster
        {
            public function auth($request) {}

            public function validAuthenticationResponse($request, $result) {}

            public function broadcast(array $channels, $event, array $payload = []): void
            {
                throw new BroadcastException('Reverb tidak terjangkau');
            }
        });
        config(['broadcasting.default' => 'down', 'broadcasting.connections.down' => ['driver' => 'down']]);

        $slot = Slot::factory()->available()->create(['sensor_id' => 'US-A']);

        $this->postJson('/api/sensor-readings', [
            'readings' => [['sensor_id' => 'US-A', 'distance_cm' => 80]],
        ], ['X-Sensor-Token' => 'secret'])->assertOk()->assertJson(['accepted' => 1, 'changed' => 1]);

        $this->assertSame(Slot::OCCUPIED, $slot->fresh()->status());
    }

    public function test_validates_payload(): void
    {
        $this->postJson('/api/sensor-readings', [
            'readings' => [['sensor_id' => 'US-A', 'distance_cm' => 'far']],
        ], ['X-Sensor-Token' => 'secret'])->assertUnprocessable();
    }

    public function test_slot_with_silent_sensor_is_unknown(): void
    {
        config(['parkir.stale_after_seconds' => 300]);

        $slot = Slot::factory()->available()->create(['last_seen_at' => now()->subMinutes(10)]);

        $this->assertSame(Slot::UNKNOWN, $slot->status());
    }
}
