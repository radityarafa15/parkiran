<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Slot extends Model
{
    /** @use HasFactory<\Database\Factories\SlotFactory> */
    use HasFactory;

    public const AVAILABLE = 'available';

    public const OCCUPIED = 'occupied';

    public const UNKNOWN = 'unknown';

    public const RESERVED = 'reserved';

    protected $fillable = ['floor_id', 'zone', 'code', 'sensor_id', 'is_occupied', 'distance_cm', 'last_seen_at', 'reserved_until'];

    protected function casts(): array
    {
        return [
            'is_occupied' => 'boolean',
            'last_seen_at' => 'datetime',
            'reserved_until' => 'datetime',
        ];
    }

    public function floor(): BelongsTo
    {
        return $this->belongsTo(Floor::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    /**
     * Batas waktu laporan terakhir agar status sensor masih dipercaya.
     */
    public static function freshCutoff(): Carbon
    {
        return now()->subSeconds(config('parkir.stale_after_seconds'));
    }

    /**
     * Batasi ke slot yang sensornya melapor dalam batas waktu.
     */
    public static function constrainFresh($query)
    {
        return $query->whereNotNull('is_occupied')->where('last_seen_at', '>=', static::freshCutoff());
    }

    /**
     * Batasi ke slot yang sedang ditahan reservasi dan tidak terdeteksi terisi.
     */
    public static function constrainReserved($query)
    {
        return $query->where('reserved_until', '>', now())
            ->where(fn ($q) => $q->whereNull('is_occupied')
                ->orWhere('is_occupied', false)
                ->orWhereNull('last_seen_at')
                ->orWhere('last_seen_at', '<', static::freshCutoff()));
    }

    /**
     * Batasi ke slot yang tidak sedang ditahan reservasi.
     */
    public static function constrainNotReserved($query)
    {
        return $query->where(fn ($q) => $q->whereNull('reserved_until')->orWhere('reserved_until', '<=', now()));
    }

    /**
     * Batasi ke slot yang boleh direservasi: sensor melapor kosong dan belum ditahan.
     */
    public static function constrainAvailable($query)
    {
        return static::constrainNotReserved(static::constrainFresh($query)->where('is_occupied', false));
    }

    public function isReserved(): bool
    {
        return $this->reserved_until !== null && $this->reserved_until->isFuture();
    }

    /**
     * Status yang ditampilkan ke pengunjung. Sensor yang lama tidak melapor
     * dianggap tidak diketahui supaya pengunjung tidak diarahkan ke slot terisi.
     * Mobil yang terdeteksi selalu menang atas reservasi: slot itu memang terisi.
     */
    public function status(): string
    {
        $fresh = $this->is_occupied !== null
            && $this->last_seen_at !== null
            && $this->last_seen_at->gte(static::freshCutoff());

        if ($fresh && $this->is_occupied) {
            return self::OCCUPIED;
        }

        if ($this->isReserved()) {
            return self::RESERVED;
        }

        return $fresh ? self::AVAILABLE : self::UNKNOWN;
    }

    /**
     * @return array{id: int, code: string, zone: string, status: string}
     */
    public function toPublicArray(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'zone' => $this->zone,
            'status' => $this->status(),
        ];
    }
}
