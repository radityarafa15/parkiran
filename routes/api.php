<?php

use App\Http\Controllers\Api\FloorController;
use App\Http\Controllers\Api\SensorReadingController;
use App\Http\Middleware\EnsureSensorToken;
use Illuminate\Support\Facades\Route;

Route::get('/floors', [FloorController::class, 'index']);
Route::get('/floors/{floor}', [FloorController::class, 'show']);

Route::post('/sensor-readings', [SensorReadingController::class, 'store'])
    ->middleware([EnsureSensorToken::class, 'throttle:600,1']);
