<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Reservation extends Model
{
    /** Dibuat, menunggu biaya admin dibayar. */
    public const PENDING = 'pending';

    /** Sudah dibayar, menunggu QR di-scan di gerbang masuk. */
    public const PAID = 'paid';

    /** Sudah masuk gerbang; jam parkir berjalan. */
    public const PARKED = 'parked';

    /** Sudah keluar gerbang. */
    public const DONE = 'done';

    /** Tidak dibayar atau tidak masuk sebelum batas waktunya. */
    public const EXPIRED = 'expired';

    /** Huruf kode tanpa karakter yang mudah tertukar (0/O, 1/I/L). */
    private const CODE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    protected $fillable = [
        'code', 'slot_id', 'name', 'plate_number', 'fee', 'status',
        'pay_before', 'paid_at', 'expires_at', 'checked_in_at', 'checked_out_at', 'parking_fee',
    ];

    protected function casts(): array
    {
        return [
            'fee' => 'integer',
            'parking_fee' => 'integer',
            'pay_before' => 'datetime',
            'paid_at' => 'datetime',
            'expires_at' => 'datetime',
            'checked_in_at' => 'datetime',
            'checked_out_at' => 'datetime',
        ];
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(Slot::class);
    }

    public static function generateCode(): string
    {
        do {
            $code = 'RSV'.collect(range(1, 8))
                ->map(fn () => self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)])
                ->implode('');
        } while (static::where('code', $code)->exists());

        return $code;
    }

    /**
     * Status saat ini. Batas waktu yang lewat langsung dianggap hangus,
     * tanpa menunggu perintah parkir:expire-reservations berjalan.
     */
    public function currentStatus(): string
    {
        return match (true) {
            $this->status === self::PENDING && $this->pay_before->isPast() => self::EXPIRED,
            $this->status === self::PAID && $this->expires_at->isPast() => self::EXPIRED,
            default => $this->status,
        };
    }

    public function statusLabel(): string
    {
        return match ($this->currentStatus()) {
            self::PENDING => 'Menunggu pembayaran',
            self::PAID => 'Menunggu masuk gerbang',
            self::PARKED => 'Sedang parkir',
            self::DONE => 'Selesai',
            self::EXPIRED => $this->paid_at ? 'Hangus' : 'Batal (tidak dibayar)',
        };
    }

    /**
     * Lama parkir sejak QR di-scan di gerbang masuk, dalam menit.
     */
    public function parkedMinutes(): ?int
    {
        if ($this->checked_in_at === null) {
            return null;
        }

        return (int) $this->checked_in_at->diffInMinutes($this->checked_out_at ?? now());
    }

    /**
     * Jam parkir yang dihitung: dibulatkan ke atas, minimal 1 jam.
     */
    public function parkedHours(): ?int
    {
        $minutes = $this->parkedMinutes();

        return $minutes === null ? null : max(1, (int) ceil($minutes / 60));
    }

    /**
     * Biaya parkir: yang ditagih saat keluar, atau perkiraan sejauh ini
     * selama mobil masih parkir.
     */
    public function parkingFee(): ?int
    {
        if ($this->parking_fee !== null) {
            return $this->parking_fee;
        }

        $hours = $this->parkedHours();

        return $hours === null ? null : $hours * config('parkir.hourly_rate');
    }

    public static function formatDuration(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        return $hours > 0 ? "{$hours} jam {$rest} menit" : "{$rest} menit";
    }

    public static function formatRupiah(int $amount): string
    {
        return 'Rp'.number_format($amount, 0, ',', '.');
    }

    public static function normalizePlate(string $plate): string
    {
        return Str::of($plate)->upper()->replaceMatches('/[^A-Z0-9]+/', ' ')->trim()->toString();
    }

    public function toApiArray(): array
    {
        $this->loadMissing('slot.floor');

        return [
            'code' => $this->code,
            'status' => $this->currentStatus(),
            'status_label' => $this->statusLabel(),
            'name' => $this->name,
            'plate_number' => $this->plate_number,
            'fee' => $this->fee,
            'slot' => [
                'id' => $this->slot->id,
                'code' => $this->slot->code,
                'floor' => $this->slot->floor->name,
            ],
            'pay_before' => $this->pay_before->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'checked_in_at' => $this->checked_in_at?->toIso8601String(),
            'checked_out_at' => $this->checked_out_at?->toIso8601String(),
            'parked_minutes' => $this->parkedMinutes(),
            'parked_hours' => $this->parkedHours(),
            'hourly_rate' => config('parkir.hourly_rate'),
            'parking_fee' => $this->parkingFee(),
            'qr_svg_url' => route('reservations.qr', $this),
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }
}
