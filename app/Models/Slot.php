<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Slot extends Model
{
    /** @use HasFactory<\Database\Factories\SlotFactory> */
    use HasFactory;

    public const AVAILABLE = 'available';

    public const OCCUPIED = 'occupied';

    public const UNKNOWN = 'unknown';

    protected $fillable = ['floor_id', 'zone', 'code', 'sensor_id', 'is_occupied', 'distance_cm', 'last_seen_at'];

    protected function casts(): array
    {
        return [
            'is_occupied' => 'boolean',
            'last_seen_at' => 'datetime',
        ];
    }

    public function floor(): BelongsTo
    {
        return $this->belongsTo(Floor::class);
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
     * Status yang ditampilkan ke pengunjung. Sensor yang lama tidak melapor
     * dianggap tidak diketahui supaya pengunjung tidak diarahkan ke slot terisi.
     */
    public function status(): string
    {
        if ($this->is_occupied === null
            || $this->last_seen_at === null
            || $this->last_seen_at->lt(static::freshCutoff())) {
            return self::UNKNOWN;
        }

        return $this->is_occupied ? self::OCCUPIED : self::AVAILABLE;
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
