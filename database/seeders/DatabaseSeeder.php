<?php

namespace Database\Seeders;

use App\Models\Floor;
use App\Models\Slot;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Tiga lantai parkir mobil. Jumlah slot per lantai masih perkiraan
     * (4 baris x 10 slot); sesuaikan dengan denah mall yang sebenarnya.
     */
    public function run(): void
    {
        foreach ([1, 2, 3] as $level) {
            $floor = Floor::updateOrCreate(['level' => $level], ['name' => "Lantai P{$level}"]);

            foreach (['A', 'B', 'C', 'D'] as $row) {
                foreach (range(1, 10) as $n) {
                    $code = sprintf('P%d-%s%02d', $level, $row, $n);

                    Slot::updateOrCreate(
                        ['code' => $code],
                        ['floor_id' => $floor->id, 'sensor_id' => "US-{$code}"],
                    );
                }
            }
        }
    }
}
