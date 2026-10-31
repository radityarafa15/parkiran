<?php

namespace App\Http\Controllers;

use App\Models\Floor;
use Illuminate\View\View;

class FloorPageController extends Controller
{
    public function index(): View
    {
        return view('floors.index', [
            'floors' => Floor::withStatusCounts()->orderBy('level')->get(),
        ]);
    }

    public function show(Floor $floor): View
    {
        return view('floors.show', [
            'floor' => $floor,
            'zones' => $floor->zoneSummaries(),
            'slotsByZone' => $floor->slots()->get()->groupBy('zone')
                ->sortKeysUsing(fn ($a, $b) => [strlen($a), $a] <=> [strlen($b), $b]),
        ]);
    }
}
