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
            'slots as available_count' => fn ($q) => Slot::constrainFresh($q)->where('is_occupied', false),
            'slots as occupied_count' => fn ($q) => Slot::constrainFresh($q)->where('is_occupied', true),
        ];
    }

    /**
     * Ringkasan lantai untuk kartu dan API.
     *
     * @return array{id: int, name: string, level: int, total: int, available: int, occupied: int, unknown: int}
     */
    public function summary(): array
    {
        if (! array_key_exists('total_count', $this->attributes)) {
            $this->loadCount(static::statusCounts());
        }

        $total = (int) $this->total_count;
        $available = (int) $this->available_count;
        $occupied = (int) $this->occupied_count;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'level' => $this->level,
            'total' => $total,
            'available' => $available,
            'occupied' => $occupied,
            'unknown' => $total - $available - $occupied,
        ];
    }

    /**
     * Ringkasan per zona di lantai ini.
     *
     * @return Collection<int, array{zone: string, total: int, available: int, occupied: int, unknown: int}>
     */
    public function zoneSummaries(): Collection
    {
        $cutoff = Slot::freshCutoff();

        return DB::table('slots')
            ->where('floor_id', $this->id)
            ->groupBy('zone')
            ->orderBy('zone')
            ->select('zone')
            ->selectRaw('count(*) as total')
            ->selectRaw('sum(case when is_occupied = ? and last_seen_at >= ? then 1 else 0 end) as available', [false, $cutoff])
            ->selectRaw('sum(case when is_occupied = ? and last_seen_at >= ? then 1 else 0 end) as occupied', [true, $cutoff])
            ->get()
            ->map(fn ($row) => [
                'zone' => $row->zone,
                'total' => (int) $row->total,
                'available' => (int) $row->available,
                'occupied' => (int) $row->occupied,
                'unknown' => (int) $row->total - (int) $row->available - (int) $row->occupied,
            ]);
    }
}
