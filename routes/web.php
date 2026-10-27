<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\SensorDashboardController;
use App\Http\Controllers\FloorPageController;
use Illuminate\Support\Facades\Route;

Route::get('/', [FloorPageController::class, 'index'])->name('floors.index');
Route::get('/lantai/{floor}', [FloorPageController::class, 'show'])->name('floors.show');

Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('/login', [AuthController::class, 'create'])->name('login');
        Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:5,1');
    });

    Route::middleware('auth')->group(function () {
        Route::get('/', SensorDashboardController::class)->name('dashboard');
        Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    });
});
