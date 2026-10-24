<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Floor extends Model
{
    /** @use HasFactory<\Database\Factories\FloorFactory> */
    use HasFactory;

    protected $fillable = ['name', 'level'];

    public function slots(): HasMany
    {
        return $this->hasMany(Slot::class)->orderBy('code');
    }

    /**
     * Jumlah slot per status untuk kartu lantai.
     *
     * @return array{total: int, available: int, occupied: int, unknown: int}
     */
    public function summary(): array
    {
        $statuses = $this->slots->map->status();

        return [
            'total' => $statuses->count(),
            'available' => $statuses->filter(fn ($s) => $s === Slot::AVAILABLE)->count(),
            'occupied' => $statuses->filter(fn ($s) => $s === Slot::OCCUPIED)->count(),
            'unknown' => $statuses->filter(fn ($s) => $s === Slot::UNKNOWN)->count(),
        ];
    }
}
