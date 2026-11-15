<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\GateController;
use App\Http\Controllers\Admin\ReservationReportController;
use App\Http\Controllers\Admin\SecurityReportController;
use App\Http\Controllers\Admin\SensorDashboardController;
use App\Http\Controllers\FloorPageController;
use App\Http\Controllers\ReservationController;
use Illuminate\Support\Facades\Route;

Route::get('/', [FloorPageController::class, 'index'])->name('floors.index');
Route::get('/lantai/{floor}', [FloorPageController::class, 'show'])->name('floors.show');

Route::prefix('reservasi')->name('reservations.')->controller(ReservationController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/baru', 'create')->name('create');
    Route::post('/', 'store')->middleware('throttle:10,1')->name('store');
    Route::get('/{reservation}', 'show')->name('show');
    Route::post('/{reservation}/bayar', 'pay')->name('pay');
    Route::post('/{reservation}/batal', 'cancel')->name('cancel');
    Route::post('/{reservation}/lapor', 'report')->middleware('throttle:5,1')->name('report');
    Route::get('/{reservation}/qr.svg', 'qr')->name('qr');
});

Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('/login', [AuthController::class, 'create'])->name('login');
        Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:5,1');
    });

    Route::middleware('auth')->group(function () {
        Route::middleware('role:admin')->group(function () {
            Route::get('/', SensorDashboardController::class)->name('dashboard');
            Route::get('/laporan', ReservationReportController::class)->name('reports');
        });

        Route::middleware('role:admin,petugas')->group(function () {
            Route::get('/gerbang', [GateController::class, 'show'])->name('gate');
            Route::post('/gerbang', [GateController::class, 'scan'])->name('gate.scan');
        });

        Route::middleware('role:admin,security')->prefix('security')->name('security')->controller(SecurityReportController::class)->group(function () {
            Route::get('/', 'index');
            Route::post('/{report}/tangani', 'handle')->name('.handle');
            Route::post('/{report}/selesai', 'resolve')->name('.resolve');
        });

        Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    });
});
