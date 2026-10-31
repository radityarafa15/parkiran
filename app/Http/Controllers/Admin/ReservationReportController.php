<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Ringkasan reservasi dan pendapatan biaya admin, dihitung per hari WIB.
 */
class ReservationReportController extends Controller
{
    private const TIMEZONE = 'Asia/Jakarta';

    private const RANGES = [1 => 'Hari ini', 7 => '7 hari', 30 => '30 hari'];

    public function __invoke(Request $request): View
    {
        $days = array_key_exists($request->integer('hari'), self::RANGES) ? $request->integer('hari') : 7;
        $from = Carbon::now(self::TIMEZONE)->startOfDay()->subDays($days - 1);

        $reservations = Reservation::with('slot.floor')
            ->where('created_at', '>=', $from->copy()->utc())
            ->latest('id')
            ->get();

        $byStatus = $reservations->countBy(fn (Reservation $r) => $r->currentStatus());
        $paid = $reservations->whereNotNull('paid_at');
        $finished = $reservations->filter(fn (Reservation $r) => $r->checked_out_at !== null);

        $perDay = collect(range(0, $days - 1))
            ->map(fn ($i) => $from->copy()->addDays($i)->toDateString())
            ->reverse()
            ->mapWithKeys(fn ($date) => [$date => $reservations->filter(
                fn (Reservation $r) => $r->created_at->timezone(self::TIMEZONE)->toDateString() === $date
            )])
            ->map(fn ($day, $date) => [
                'date' => Carbon::parse($date, self::TIMEZONE),
                'created' => $day->count(),
                'paid' => $day->whereNotNull('paid_at')->count(),
                'revenue' => $day->whereNotNull('paid_at')->sum('fee'),
                'parking' => $day->sum('parking_fee'),
                'expired' => $day->filter(fn (Reservation $r) => $r->currentStatus() === Reservation::EXPIRED && $r->paid_at)->count(),
            ]);

        return view('admin.reports', [
            'days' => $days,
            'ranges' => self::RANGES,
            'stats' => [
                'created' => $reservations->count(),
                'paid' => $paid->count(),
                'revenue' => $paid->sum('fee'),
                'parking' => $reservations->sum('parking_fee'),
                'waiting' => $byStatus->get(Reservation::PAID, 0),
                'parked' => Reservation::where('status', Reservation::PARKED)->count(),
                'done' => $byStatus->get(Reservation::DONE, 0),
                'forfeited' => $paid->filter(fn (Reservation $r) => $r->currentStatus() === Reservation::EXPIRED)->count(),
                'unpaid' => $reservations->filter(fn (Reservation $r) => $r->currentStatus() === Reservation::EXPIRED && ! $r->paid_at)->count(),
                'avg_minutes' => $finished->isEmpty() ? null : (int) round($finished->avg(fn (Reservation $r) => $r->parkedMinutes())),
            ],
            'perDay' => $perDay,
            'recent' => $reservations->take(50),
        ]);
    }
}
