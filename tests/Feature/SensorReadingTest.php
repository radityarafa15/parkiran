<?php

namespace Tests\Feature;

use App\Models\Slot;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->assertJson(['accepted' => 2, 'unknown_sensors' => ['US-NOPE']]);

        $this->assertSame(Slot::OCCUPIED, $a->fresh()->status());
        $this->assertSame(Slot::AVAILABLE, $b->fresh()->status());
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
