<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SensorReadingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class SensorReadingController extends Controller
{
    /**
     * Terima satu batch pembacaan dari gateway lantai.
     */
    public function store(Request $request, SensorReadingService $service): JsonResponse
    {
        $data = $request->validate([
            'readings' => ['required', 'array', 'min:1', 'max:500'],
            'readings.*.sensor_id' => ['required', 'string', 'max:64'],
            'readings.*.distance_cm' => ['required', 'integer', 'min:0', 'max:1000'],
            'readings.*.measured_at' => ['nullable', 'date'],
        ]);

        $readings = array_map(fn (array $reading) => [
            'sensor_id' => $reading['sensor_id'],
            'distance_cm' => $reading['distance_cm'],
            // Jam gateway bisa melenceng; jangan terima waktu dari masa depan.
            'measured_at' => isset($reading['measured_at'])
                ? Carbon::parse($reading['measured_at'])->min(now())
                : null,
        ], $data['readings']);

        return response()->json($service->recordMany($readings));
    }
}
