<?php

namespace Tests\Feature;

use App\Models\Slot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_slots_are_grouped_in_blocks_of_the_floor_plan_and_old_slots_removed(): void
    {
        config(['parkir.layout' => [1 => 125]]);

        // Susunan lama: zona 100 slot.
        config(['parkir.zone_size' => 100]);
        $this->seed();
        $this->assertTrue(Slot::where('code', 'P1-A100')->exists());

        config(['parkir.zone_size' => 60]);
        $this->seed();

        $this->assertSame(125, Slot::count());
        $this->assertSame(['A' => 60, 'B' => 60, 'C' => 5], Slot::selectRaw('zone, count(*) as n')->groupBy('zone')->pluck('n', 'zone')->map(fn ($n) => (int) $n)->all());
        $this->assertFalse(Slot::where('code', 'P1-A100')->exists());
        $this->assertTrue(Slot::where('code', 'P1-C005')->exists());
    }
}
