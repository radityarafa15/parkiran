<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ReservationException;
use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\Slot;
use App\Services\ReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReservationController extends Controller
{
    public function store(Request $request, ReservationService $service): JsonResponse
    {
        $data = $request->validate([
            'slot_id' => ['required', 'integer', 'exists:slots,id'],
            'name' => ['required', 'string', 'max:60'],
            'plate_number' => ['required', 'string', 'max:16', 'regex:/^[A-Za-z]{1,2}\s*\d{1,4}\s*[A-Za-z]{0,3}$/'],
        ]);

        try {
            $reservation = $service->reserve(Slot::findOrFail($data['slot_id']), $data['name'], $data['plate_number']);
        } catch (ReservationException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json(['data' => $reservation->toApiArray()], 201);
    }

    public function show(Reservation $reservation): JsonResponse
    {
        return response()->json(['data' => $reservation->toApiArray()]);
    }

    /**
     * Pembayaran simulasi; lihat ReservationService::pay.
     */
    public function pay(Reservation $reservation, ReservationService $service): JsonResponse
    {
        try {
            $service->pay($reservation);
        } catch (ReservationException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json(['data' => $reservation->toApiArray()]);
    }
}
