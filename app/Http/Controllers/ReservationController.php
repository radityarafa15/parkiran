<?php

namespace App\Http\Controllers;

use App\Exceptions\ReservationException;
use App\Models\Reservation;
use App\Models\Slot;
use App\Services\ReservationService;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class ReservationController extends Controller
{
    /** Kode reservasi yang pernah dibuat di browser ini, supaya bisa dibuka lagi. */
    private const SESSION_KEY = 'reservations';

    public function index(Request $request): View
    {
        return view('reservations.index', [
            'reservations' => Reservation::with('slot.floor')
                ->whereIn('code', $request->session()->get(self::SESSION_KEY, []))
                ->latest('id')
                ->get(),
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        $slot = Slot::with('floor')->findOrFail($request->integer('slot'));

        if ($slot->status() !== Slot::AVAILABLE) {
            return redirect()->route('floors.show', $slot->floor)
                ->with('error', "Slot {$slot->code} sedang tidak tersedia. Silakan pilih slot hijau lain.");
        }

        return view('reservations.create', [
            'slot' => $slot,
            'config' => config('parkir.reservation'),
        ]);
    }

    public function store(Request $request, ReservationService $service): RedirectResponse
    {
        $data = $request->validate([
            'slot_id' => ['required', 'integer', 'exists:slots,id'],
            'name' => ['required', 'string', 'max:60'],
            'plate_number' => ['required', 'string', 'max:16', 'regex:/^[A-Za-z]{1,2}\s*\d{1,4}\s*[A-Za-z]{0,3}$/'],
        ], [
            'plate_number.regex' => 'Format plat nomor tidak dikenali, contoh: B 1234 XYZ.',
        ]);

        $slot = Slot::with('floor')->findOrFail($data['slot_id']);

        try {
            $reservation = $service->reserve($slot, $data['name'], $data['plate_number']);
        } catch (ReservationException $e) {
            return redirect()->route('floors.show', $slot->floor)->with('error', $e->getMessage());
        }

        $request->session()->push(self::SESSION_KEY, $reservation->code);

        return redirect()->route('reservations.show', $reservation);
    }

    public function show(Reservation $reservation): View
    {
        return view('reservations.show', [
            'reservation' => $reservation->load('slot.floor'),
        ]);
    }

    /**
     * Pembayaran simulasi. Nanti diganti alur payment gateway (misalnya Midtrans).
     */
    public function pay(Reservation $reservation, ReservationService $service): RedirectResponse
    {
        try {
            $service->pay($reservation);
        } catch (ReservationException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('reservations.show', $reservation)
            ->with('status', 'Pembayaran berhasil. Tunjukkan QR ini di gerbang masuk.');
    }

    public function qr(Reservation $reservation): Response
    {
        $svg = (new QRCode(new QROptions([
            'outputType' => QROutputInterface::MARKUP_SVG,
            'outputBase64' => false,
            'svgAddXmlHeader' => false,
            'addQuietzone' => true,
        ])))->render($reservation->code);

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }
}
