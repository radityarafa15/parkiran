<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Floor;
use App\Models\Slot;
use Illuminate\Http\JsonResponse;

class FloorController extends Controller
{
    public function index(): JsonResponse
    {
        $floors = Floor::with('slots')->orderBy('level')->get();

        return response()->json([
            'data' => $floors->map(fn (Floor $floor) => [
                'id' => $floor->id,
                'name' => $floor->name,
                'level' => $floor->level,
                ...$floor->summary(),
            ]),
        ]);
    }

    public function show(Floor $floor): JsonResponse
    {
        $floor->load('slots');

        return response()->json([
            'data' => [
                'id' => $floor->id,
                'name' => $floor->name,
                'level' => $floor->level,
                ...$floor->summary(),
                'slots' => $floor->slots->map(fn (Slot $slot) => [
                    'id' => $slot->id,
                    'code' => $slot->code,
                    'status' => $slot->status(),
                    'last_seen_at' => $slot->last_seen_at?->toIso8601String(),
                ]),
            ],
        ]);
    }
}
