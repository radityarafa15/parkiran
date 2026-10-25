<?php

use App\Http\Controllers\FloorPageController;
use Illuminate\Support\Facades\Route;

Route::get('/', [FloorPageController::class, 'index'])->name('floors.index');
Route::get('/lantai/{floor}', [FloorPageController::class, 'show'])->name('floors.show');
