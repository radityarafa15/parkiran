<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\ReservationException;
use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Services\ReservationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Halaman petugas gerbang. Scanner QR USB bekerja seperti keyboard: mengetik
 * kode reservasi lalu Enter, jadi cukup satu kolom isian yang selalu fokus.
 */
class GateController extends Controller
{
    public function show(Request $request): View
    {
        $direction = $request->query('arah') === 'keluar' ? 'keluar' : 'masuk';

        return view('admin.gate', [
            'direction' => $direction,
            'waiting' => Reservation::with('slot.floor')
                ->where('status', $direction === 'masuk' ? Reservation::PAID : Reservation::PARKED)
                ->when($direction === 'masuk', fn ($q) => $q->where('expires_at', '>', now()))
                ->orderBy($direction === 'masuk' ? 'expires_at' : 'checked_in_at')
                ->limit(50)
                ->get(),
        ]);
    }

    public function scan(Request $request, ReservationService $service): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:32'],
            'arah' => ['required', 'in:masuk,keluar'],
        ]);

        $back = redirect()->route('admin.gate', ['arah' => $data['arah']]);
        $code = Str::upper(trim($data['code']));
        $reservation = Reservation::with('slot.floor')->where('code', $code)->first();

        if ($reservation === null) {
            return $back->with('error', "Kode {$code} tidak ditemukan.");
        }

        try {
            if ($data['arah'] === 'masuk') {
                $service->checkIn($reservation);
                $message = "Silakan masuk. {$reservation->plate_number} menuju slot {$reservation->slot->code} ({$reservation->slot->floor->name}). Jam parkir mulai {$reservation->checked_in_at->timezone('Asia/Jakarta')->format('H:i')} WIB.";
            } else {
                $service->checkOut($reservation);
                $message = "Silakan keluar. {$reservation->plate_number} parkir ".Reservation::formatDuration($reservation->parkedMinutes())
                    ." ({$reservation->parkedHours()} jam × ".Reservation::formatRupiah(config('parkir.hourly_rate'))
                    .'). Biaya parkir: '.Reservation::formatRupiah($reservation->parking_fee).'.';
            }
        } catch (ReservationException $e) {
            return $back->with('error', $e->getMessage());
        }

        return $back->with('status', $message);
    }
}
