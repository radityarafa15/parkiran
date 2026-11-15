<?php

namespace App\Services;

use App\Exceptions\ReservationException;
use App\Models\Reservation;
use App\Models\Slot;
use App\Models\SlotReport;
use App\Models\User;
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
        $plateNumber = Reservation::normalizePlate($plateNumber);

        $reservation = DB::transaction(function () use ($slot, $name, $plateNumber, $config, $payBefore) {
            // Aturan 1 orang 1 mobil: satu plat hanya boleh memegang satu slot.
            $active = Reservation::where('plate_number', $plateNumber)->active()->first();

            if ($active !== null) {
                throw new ReservationException(
                    // Kode dan QR tidak disebut: siapa pun bisa mengetik plat orang lain.
                    "Plat {$plateNumber} sudah punya 1 reservasi aktif. Selesaikan atau batalkan reservasi itu dulu sebelum memesan slot lain.",
                    $active,
                );
            }

            // UPDATE bersyarat: hanya satu permintaan yang berhasil menahan slot ini.
            $held = Slot::constrainAvailable(Slot::whereKey($slot->id))->update(['reserved_until' => $payBefore]);

            if ($held === 0) {
                throw new ReservationException("Slot {$slot->code} sudah tidak tersedia. Silakan pilih slot lain.");
            }

            return Reservation::create([
                'code' => Reservation::generateCode(),
                'slot_id' => $slot->id,
                'name' => $name,
                'plate_number' => $plateNumber,
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
     * QR di-scan di gerbang keluar: jam parkir berhenti dan biaya parkir dihitung.
     */
    public function checkOut(Reservation $reservation): Reservation
    {
        $this->ensureStatus($reservation, Reservation::PARKED);

        $reservation->checked_out_at = now();

        return $this->update($reservation, [
            'status' => Reservation::DONE,
            'checked_out_at' => $reservation->checked_out_at,
            'parking_fee' => $reservation->parkedHours() * config('parkir.hourly_rate'),
        ], fn () => null);
    }

    /**
     * Pengunjung membatalkan sebelum masuk gerbang; slot langsung dilepas.
     * Biaya admin dikembalikan penuh jika batal dalam batas refund setelah bayar.
     * Refund masih simulasi, sama seperti pembayarannya.
     */
    public function cancel(Reservation $reservation): Reservation
    {
        if (! $reservation->canCancel()) {
            throw new ReservationException(match ($reservation->currentStatus()) {
                Reservation::PARKED, Reservation::DONE => "Reservasi {$reservation->code} sudah masuk gerbang, jadi tidak bisa dibatalkan.",
                Reservation::CANCELLED => "Reservasi {$reservation->code} sudah dibatalkan.",
                default => "Reservasi {$reservation->code} sudah hangus.",
            });
        }

        return $this->update($reservation, [
            'status' => Reservation::CANCELLED,
            'cancelled_at' => now(),
            'refund_amount' => $reservation->refundIfCancelledNow(),
        ], fn () => null);
    }

    /**
     * Laporan pengunjung ke security. Jika slotnya ditempati mobil lain,
     * slot kosong terdekat di lantai yang sama langsung ditahan sebagai
     * pengganti; QR tetap sama.
     */
    public function report(Reservation $reservation, string $type, ?string $note): SlotReport
    {
        $status = $reservation->currentStatus();

        if (! in_array($status, [Reservation::PAID, Reservation::PARKED], true)) {
            throw new ReservationException("Reservasi {$reservation->code} tidak aktif, jadi tidak bisa dilaporkan.");
        }

        $original = $reservation->slot;
        $replacement = null;

        $report = DB::transaction(function () use ($reservation, $type, $note, $original, &$replacement) {
            if ($type === SlotReport::OCCUPIED) {
                $replacement = $this->holdReplacement($reservation);
            }

            return $reservation->reports()->create([
                'slot_id' => $original->id,
                'replacement_slot_id' => $replacement?->id,
                'type' => $type,
                'note' => $note,
                'status' => SlotReport::NEW,
            ]);
        });

        if ($replacement !== null) {
            $this->broadcaster->slotsChanged(collect([$original->id => $original->refresh(), $replacement->id => $replacement]));
        }

        return $report;
    }

    public function startHandling(SlotReport $report, User $user): SlotReport
    {
        if ($report->status === SlotReport::NEW) {
            $report->update(['status' => SlotReport::HANDLING, 'handled_by' => $user->id, 'handled_at' => now()]);
        }

        return $report;
    }

    public function resolve(SlotReport $report, User $user): SlotReport
    {
        $report->update([
            'status' => SlotReport::DONE,
            'handled_by' => $report->handled_by ?? $user->id,
            'handled_at' => $report->handled_at ?? now(),
            'resolved_at' => now(),
        ]);

        return $report;
    }

    /**
     * Pindahkan reservasi ke slot kosong terdekat (zona yang sama dulu) dan
     * lepaskan slot lama. Mengembalikan null jika lantai itu penuh.
     */
    private function holdReplacement(Reservation $reservation): ?Slot
    {
        $original = $reservation->slot;
        $holdUntil = collect([
            $original->reserved_until,
            now()->addMinutes(config('parkir.reservation.drive_in_minutes')),
        ])->filter()->max();

        $candidates = Slot::constrainAvailable(Slot::where('floor_id', $original->floor_id)->whereKeyNot($original->id))
            ->orderByRaw('CASE WHEN zone = ? THEN 0 ELSE 1 END', [$original->zone])
            ->orderByRaw('ABS(id - ?)', [$original->id])
            ->limit(5)
            ->get();

        foreach ($candidates as $candidate) {
            // UPDATE bersyarat seperti saat reservasi: jangan merebut slot yang baru ditahan orang lain.
            if (Slot::constrainAvailable(Slot::whereKey($candidate->id))->update(['reserved_until' => $holdUntil]) === 1) {
                $original->update(['reserved_until' => null]);
                $reservation->update(['slot_id' => $candidate->id]);
                $reservation->setRelation('slot', $candidate->refresh());

                return $candidate;
            }
        }

        return null;
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
            Reservation::PARKED => "QR sudah dipakai: {$reservation->code} ({$reservation->plate_number}) masuk pukul {$this->wib($reservation->checked_in_at)} hari ini. Jangan buka palang.",
            Reservation::DONE => "QR sudah dipakai: {$reservation->code} sudah keluar pukul {$this->wib($reservation->checked_out_at)}.",
            Reservation::EXPIRED => "Reservasi {$reservation->code} sudah hangus.",
            Reservation::CANCELLED => "Reservasi {$reservation->code} sudah dibatalkan.",
        });
    }

    private function wib(?Carbon $time): string
    {
        return $time?->timezone('Asia/Jakarta')->format('H:i').' WIB';
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
