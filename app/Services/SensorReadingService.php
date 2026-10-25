<?php

namespace App\Services;

use App\Events\FloorSummaryUpdated;
use App\Events\SlotsChanged;
use App\Models\Floor;
use App\Models\Slot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SensorReadingService
{
    /** Batas slot per pesan WebSocket agar di bawah batas ukuran pesan Reverb. */
    private const SLOTS_PER_MESSAGE = 100;

    /**
     * Simpan satu batch pembacaan sensor ultrasonik, lalu siarkan slot yang
     * statusnya berubah dan ringkasan lantainya.
     *
     * @param  iterable<array{sensor_id: string, distance_cm: int, measured_at?: Carbon|null}>  $readings
     * @return array{accepted: int, changed: int, unknown_sensors: array<int, string>}
     */
    public function recordMany(iterable $readings): array
    {
        $readings = collect($readings);
        $slots = Slot::whereIn('sensor_id', $readings->pluck('sensor_id')->unique())->get()->keyBy('sensor_id');
        $threshold = config('parkir.occupied_threshold_cm');

        $accepted = 0;
        $unknownSensors = [];
        /** @var Collection<int, Slot> $changed */
        $changed = collect();

        // Satu transaksi per batch: ratusan UPDATE kecil jauh lebih cepat.
        DB::transaction(function () use ($readings, $slots, $threshold, &$accepted, &$unknownSensors, $changed) {
            foreach ($readings as $reading) {
                $slot = $slots->get($reading['sensor_id']);

                if ($slot === null) {
                    $unknownSensors[] = $reading['sensor_id'];

                    continue;
                }

                $before = $slot->status();

                $slot->fill([
                    'distance_cm' => $reading['distance_cm'],
                    'is_occupied' => $reading['distance_cm'] < $threshold,
                    'last_seen_at' => $reading['measured_at'] ?? now(),
                ])->save();

                $accepted++;

                if ($slot->status() !== $before) {
                    $changed->put($slot->id, $slot);
                }
            }
        });

        $this->broadcastChanges($changed);

        return [
            'accepted' => $accepted,
            'changed' => $changed->count(),
            'unknown_sensors' => array_values(array_unique($unknownSensors)),
        ];
    }

    /**
     * @param  Collection<int, Slot>  $changed
     */
    private function broadcastChanges(Collection $changed): void
    {
        if ($changed->isEmpty()) {
            return;
        }

        foreach ($changed->groupBy('floor_id') as $floorId => $slots) {
            foreach ($slots->chunk(self::SLOTS_PER_MESSAGE) as $chunk) {
                SlotsChanged::dispatch($floorId, $chunk->map->toPublicArray()->values()->all());
            }
        }

        Floor::withStatusCounts()
            ->whereIn('id', $changed->pluck('floor_id')->unique())
            ->get()
            ->each(fn (Floor $floor) => FloorSummaryUpdated::dispatch($floor->summary()));
    }
}
