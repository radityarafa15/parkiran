<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Floor;
use App\Models\Slot;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SensorDashboardController extends Controller
{
    /**
     * Sensor yang tidak melapor dalam batas waktu (atau belum pernah melapor),
     * supaya teknisi tahu sensor mana yang perlu dicek di lapangan.
     */
    public function __invoke(Request $request): View
    {
        $request->validate(['floor' => ['nullable', 'integer', 'exists:floors,id']]);

        $floors = Floor::withStatusCounts()->orderBy('level')->get();

        $offline = Slot::with('floor')
            ->where(fn ($q) => $q->whereNull('last_seen_at')
                ->orWhereNull('is_occupied')
                ->orWhere('last_seen_at', '<', Slot::freshCutoff()))
            ->when($request->integer('floor'), fn ($q, $floor) => $q->where('floor_id', $floor))
            // Yang paling lama diam di atas; yang belum pernah melapor paling akhir.
            ->orderByRaw('last_seen_at is null')
            ->orderBy('last_seen_at')
            ->orderBy('code')
            ->paginate(50)
            ->withQueryString();

        return view('admin.dashboard', [
            'floors' => $floors,
            'offline' => $offline,
            'selectedFloor' => $request->integer('floor') ?: null,
            'staleMinutes' => intdiv(config('parkir.stale_after_seconds'), 60),
        ]);
    }
}
