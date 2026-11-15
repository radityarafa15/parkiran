<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SlotReport;
use App\Services\ReservationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Halaman security: laporan pengunjung yang belum selesai di atas,
 * lalu yang baru selesai sebagai riwayat.
 */
class SecurityReportController extends Controller
{
    public function index(): View
    {
        $with = ['reservation', 'slot.floor', 'replacementSlot', 'handler'];

        return view('admin.security', [
            'open' => SlotReport::with($with)
                ->where('status', '!=', SlotReport::DONE)
                ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [SlotReport::NEW])
                ->oldest('id')
                ->get(),
            'done' => SlotReport::with($with)
                ->where('status', SlotReport::DONE)
                ->latest('resolved_at')
                ->limit(20)
                ->get(),
        ]);
    }

    public function handle(Request $request, SlotReport $report, ReservationService $service): RedirectResponse
    {
        $service->startHandling($report, $request->user());

        return redirect()->route('admin.security')->with('status', "Laporan slot {$report->slot->code} sedang kamu tangani.");
    }

    public function resolve(Request $request, SlotReport $report, ReservationService $service): RedirectResponse
    {
        $service->resolve($report, $request->user());

        return redirect()->route('admin.security')->with('status', "Laporan slot {$report->slot->code} selesai.");
    }
}
