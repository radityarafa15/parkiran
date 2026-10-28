<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
     * Hitung slot per status langsung di database, supaya ribuan slot
     * tidak perlu dimuat ke memori hanya untuk ringkasan.
     */
    public function scopeWithStatusCounts(Builder $query): void
    {
        $query->withCount(static::statusCounts());
    }

    private static function statusCounts(): array
    {
        return [
            'slots as total_count',
            'slots as available_count' => fn ($q) => Slot::constrainAvailable($q),
            'slots as occupied_count' => fn ($q) => Slot::constrainFresh($q)->where('is_occupied', true),
            'slots as reserved_count' => fn ($q) => Slot::constrainReserved($q),
        ];
    }

    /**
     * Ringkasan lantai untuk kartu dan API.
     *
     * @return array{id: int, name: string, level: int, total: int, available: int, occupied: int, reserved: int, unknown: int}
     */
    public function summary(): array
    {
        if (! array_key_exists('total_count', $this->attributes)) {
            $this->loadCount(static::statusCounts());
        }

        $total = (int) $this->total_count;
        $available = (int) $this->available_count;
        $occupied = (int) $this->occupied_count;
        $reserved = (int) $this->reserved_count;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'level' => $this->level,
            'total' => $total,
            'available' => $available,
            'occupied' => $occupied,
            'reserved' => $reserved,
            'unknown' => $total - $available - $occupied - $reserved,
        ];
    }

    /**
     * Ringkasan per zona di lantai ini.
     *
     * @return Collection<int, array{zone: string, total: int, available: int, occupied: int, reserved: int, unknown: int}>
     */
    public function zoneSummaries(): Collection
    {
        $cutoff = Slot::freshCutoff();
        $now = now();

        return DB::table('slots')
            ->where('floor_id', $this->id)
            ->groupBy('zone')
            ->orderBy('zone')
            ->select('zone')
            ->selectRaw('count(*) as total')
            ->selectRaw('sum(case when is_occupied = ? and last_seen_at >= ? and (reserved_until is null or reserved_until <= ?) then 1 else 0 end) as available', [false, $cutoff, $now])
            ->selectRaw('sum(case when is_occupied = ? and last_seen_at >= ? then 1 else 0 end) as occupied', [true, $cutoff])
            ->selectRaw('sum(case when reserved_until > ? and (is_occupied is null or is_occupied = ? or last_seen_at is null or last_seen_at < ?) then 1 else 0 end) as reserved', [$now, false, $cutoff])
            ->get()
            ->map(fn ($row) => [
                'zone' => $row->zone,
                'total' => (int) $row->total,
                'available' => (int) $row->available,
                'occupied' => (int) $row->occupied,
                'reserved' => (int) $row->reserved,
                'unknown' => (int) $row->total - (int) $row->available - (int) $row->occupied - (int) $row->reserved,
            ]);
    }
}
