<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Floor;
use App\Models\Slot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FloorController extends Controller
{
    public function index(): JsonResponse
    {
        $floors = Floor::withStatusCounts()->orderBy('level')->get();

        return response()->json([
            'data' => $floors->map->summary(),
        ]);
    }

    public function show(Floor $floor): JsonResponse
    {
        return response()->json([
            'data' => [
                ...$floor->summary(),
                'zones' => $floor->zoneSummaries(),
            ],
        ]);
    }

    /**
     * Status slot di satu lantai, bisa disaring per zona.
     */
    public function slots(Request $request, Floor $floor): JsonResponse
    {
        $request->validate(['zone' => ['nullable', 'string', 'max:8']]);

        $slots = $floor->slots()
            ->when($request->query('zone'), fn ($q, $zone) => $q->where('zone', $zone))
            ->get();

        return response()->json([
            'data' => $slots->map(fn (Slot $slot) => $slot->toPublicArray()),
        ]);
    }

    /**
     * Alamat WebSocket untuk klien (web dan mobile).
     */
    public function realtime(): JsonResponse
    {
        return response()->json(['data' => config('parkir.realtime')]);
    }
}
