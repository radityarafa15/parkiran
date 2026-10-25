<?php

namespace Database\Seeders;

use App\Models\Floor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Lantai dan slot mobil sesuai config('parkir.layout'), total 6.995 slot.
     * Slot dibagi ke zona A, B, C, ... berisi config('parkir.zone_size') slot.
     * Kode slot: P1-A001, ID sensor: US-P1-A001.
     */
    public function run(): void
    {
        $zoneSize = config('parkir.zone_size');
        $now = now();

        foreach (config('parkir.layout') as $level => $count) {
            $floor = Floor::updateOrCreate(['level' => $level], ['name' => "Lantai P{$level}"]);

            $rows = [];

            for ($i = 0; $i < $count; $i++) {
                $zone = $this->zoneName(intdiv($i, $zoneSize));
                $code = sprintf('P%d-%s%03d', $level, $zone, $i % $zoneSize + 1);

                $rows[] = [
                    'floor_id' => $floor->id,
                    'zone' => $zone,
                    'code' => $code,
                    'sensor_id' => "US-{$code}",
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('slots')->upsert($chunk, ['code'], ['floor_id', 'zone', 'sensor_id', 'updated_at']);
            }
        }
    }

    /** 0 => A, 25 => Z, 26 => AA. */
    private function zoneName(int $index): string
    {
        $name = '';

        for ($index++; $index > 0; $index = intdiv($index - 1, 26)) {
            $name = chr(65 + ($index - 1) % 26).$name;
        }

        return $name;
    }
}
