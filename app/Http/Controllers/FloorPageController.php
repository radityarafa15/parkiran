<?php

namespace App\Http\Controllers;

use App\Models\Floor;
use Illuminate\View\View;

class FloorPageController extends Controller
{
    public function index(): View
    {
        return view('floors.index', [
            'floors' => Floor::with('slots')->orderBy('level')->get(),
        ]);
    }

    public function show(Floor $floor): View
    {
        return view('floors.show', [
            'floor' => $floor->load('slots'),
        ]);
    }
}
