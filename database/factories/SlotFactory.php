<?php

namespace Database\Factories;

use App\Models\Floor;
use App\Models\Slot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Slot>
 */
class SlotFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $code = strtoupper($this->faker->unique()->bothify('?##'));

        return [
            'floor_id' => Floor::factory(),
            'zone' => 'A',
            'code' => $code,
            'sensor_id' => "US-{$code}",
        ];
    }

    public function available(): static
    {
        return $this->state(['is_occupied' => false, 'distance_cm' => 260, 'last_seen_at' => now()]);
    }

    public function occupied(): static
    {
        return $this->state(['is_occupied' => true, 'distance_cm' => 90, 'last_seen_at' => now()]);
    }
}
