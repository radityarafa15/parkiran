<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Slot extends Model
{
    /** @use HasFactory<\Database\Factories\SlotFactory> */
    use HasFactory;

    public const AVAILABLE = 'available';

    public const OCCUPIED = 'occupied';

    public const UNKNOWN = 'unknown';

    protected $fillable = ['floor_id', 'code', 'sensor_id', 'is_occupied', 'distance_cm', 'last_seen_at'];

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
     * Status yang ditampilkan ke pengunjung. Sensor yang lama tidak melapor
     * dianggap tidak diketahui supaya pengunjung tidak diarahkan ke slot terisi.
     */
    public function status(): string
    {
        $staleAfter = config('parkir.stale_after_seconds');

        if ($this->is_occupied === null
            || $this->last_seen_at === null
            || $this->last_seen_at->lt(now()->subSeconds($staleAfter))) {
            return self::UNKNOWN;
        }

        return $this->is_occupied ? self::OCCUPIED : self::AVAILABLE;
    }
}
