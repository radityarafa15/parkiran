<?php

namespace App\Services;

use App\Models\Slot;
use Illuminate\Support\Carbon;

class SensorReadingService
{
    /**
     * Simpan satu pembacaan sensor ultrasonik ke slot yang dipantaunya.
     * Mengembalikan null jika sensor tidak terdaftar.
     */
    public function record(string $sensorId, int $distanceCm, ?Carbon $measuredAt = null): ?Slot
    {
        $slot = Slot::where('sensor_id', $sensorId)->first();

        if ($slot === null) {
            return null;
        }

        $slot->update([
            'distance_cm' => $distanceCm,
            'is_occupied' => $distanceCm < config('parkir.occupied_threshold_cm'),
            'last_seen_at' => $measuredAt ?? now(),
        ]);

        return $slot;
    }
}
