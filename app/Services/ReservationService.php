<?php

namespace App\Services;

use App\Exceptions\ReservationException;
use App\Models\Reservation;
use App\Models\Slot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReservationService
{
    public function __construct(private SlotBroadcaster $broadcaster) {}

    /**
     * Tahan slot yang sedang kosong untuk satu pengguna sampai batas waktu bayar.
     */
    public function reserve(Slot $slot, string $name, string $plateNumber): Reservation
    {
        $config = config('parkir.reservation');
        $payBefore = now()->addMinutes($config['payment_minutes']);

        $reservation = DB::transaction(function () use ($slot, $name, $plateNumber, $config, $payBefore) {
            // UPDATE bersyarat: hanya satu permintaan yang berhasil menahan slot ini.
            $held = Slot::constrainAvailable(Slot::whereKey($slot->id))->update(['reserved_until' => $payBefore]);

            if ($held === 0) {
                throw new ReservationException("Slot {$slot->code} sudah tidak tersedia. Silakan pilih slot lain.");
            }

            return Reservation::create([
                'code' => Reservation::generateCode(),
                'slot_id' => $slot->id,
                'name' => $name,
                'plate_number' => Reservation::normalizePlate($plateNumber),
                'fee' => $config['fee'],
                'status' => Reservation::PENDING,
                'pay_before' => $payBefore,
            ]);
        });

        $this->broadcast($reservation);

        return $reservation;
    }

    /**
     * Catat pembayaran biaya admin. Masa berlaku reservasi mulai dihitung dari sini.
     * Pembayaran masih simulasi; payment gateway memanggil ini setelah transaksi sukses.
     */
    public function pay(Reservation $reservation): Reservation
    {
        $this->ensureStatus($reservation, Reservation::PENDING);

        $now = now();

        return $this->update($reservation, [
            'status' => Reservation::PAID,
            'paid_at' => $now,
            'expires_at' => $now->copy()->addMinutes(config('parkir.reservation.valid_minutes')),
        ], fn (Reservation $r) => $r->expires_at);
    }

    /**
     * QR di-scan di gerbang masuk: jam parkir mulai dihitung.
     */
    public function checkIn(Reservation $reservation): Reservation
    {
        $this->ensureStatus($reservation, Reservation::PAID);

        $now = now();

        return $this->update($reservation, [
            'status' => Reservation::PARKED,
            'checked_in_at' => $now,
        ], fn () => $now->copy()->addMinutes(config('parkir.reservation.drive_in_minutes')));
    }

    /**
     * QR di-scan di gerbang keluar: jam parkir berhenti.
     */
    public function checkOut(Reservation $reservation): Reservation
    {
        $this->ensureStatus($reservation, Reservation::PARKED);

        return $this->update($reservation, [
            'status' => Reservation::DONE,
            'checked_out_at' => now(),
        ], fn () => null);
    }

    /**
     * Tandai reservasi yang lewat batas waktu sebagai hangus dan lepaskan slot
     * yang masa tahannya habis, lalu siarkan supaya slot kembali tampil kosong.
     *
     * @return int jumlah reservasi yang hangus
     */
    public function expireOverdue(): int
    {
        $now = now();

        $expired = Reservation::where(fn ($q) => $q
            ->where(fn ($q) => $q->where('status', Reservation::PENDING)->where('pay_before', '<=', $now))
            ->orWhere(fn ($q) => $q->where('status', Reservation::PAID)->where('expires_at', '<=', $now)))
            ->update(['status' => Reservation::EXPIRED, 'updated_at' => $now]);

        $released = Slot::where('reserved_until', '<=', $now)->get();

        if ($released->isNotEmpty()) {
            Slot::whereKey($released->modelKeys())->update(['reserved_until' => null]);
            $released->each(fn (Slot $slot) => $slot->reserved_until = null);
            $this->broadcaster->slotsChanged($released->keyBy('id'));
        }

        return $expired;
    }

    private function ensureStatus(Reservation $reservation, string $expected): void
    {
        $current = $reservation->currentStatus();

        if ($current === $expected) {
            return;
        }

        throw new ReservationException(match ($current) {
            Reservation::PENDING => "Reservasi {$reservation->code} belum dibayar.",
            Reservation::PAID => "Reservasi {$reservation->code} belum masuk gerbang.",
            Reservation::PARKED => "Reservasi {$reservation->code} sudah masuk gerbang.",
            Reservation::DONE => "Reservasi {$reservation->code} sudah selesai.",
            Reservation::EXPIRED => "Reservasi {$reservation->code} sudah hangus.",
        });
    }

    /**
     * Simpan perubahan reservasi dan masa tahan slotnya dalam satu transaksi.
     *
     * @param  callable(Reservation): ?Carbon  $holdUntil
     */
    private function update(Reservation $reservation, array $attributes, callable $holdUntil): Reservation
    {
        DB::transaction(function () use ($reservation, $attributes, $holdUntil) {
            $reservation->update($attributes);
            $reservation->slot->update(['reserved_until' => $holdUntil($reservation)]);
        });

        $this->broadcast($reservation);

        return $reservation;
    }

    private function broadcast(Reservation $reservation): void
    {
        $slot = $reservation->slot;
        $this->broadcaster->slotsChanged(collect([$slot->id => $slot]));
    }
}
