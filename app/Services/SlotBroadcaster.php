<?php

namespace App\Services;

use App\Events\FloorSummaryUpdated;
use App\Events\SlotsChanged;
use App\Models\Floor;
use App\Models\Slot;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Support\Collection;

class SlotBroadcaster
{
    /** Batas slot per pesan WebSocket agar di bawah batas ukuran pesan Reverb. */
    private const SLOTS_PER_MESSAGE = 100;

    /**
     * Siarkan slot yang statusnya berubah beserta ringkasan lantainya.
     *
     * @param  Collection<int, Slot>  $changed
     */
    public function slotsChanged(Collection $changed): void
    {
        if ($changed->isEmpty()) {
            return;
        }

        // Data sudah tersimpan. Jika Reverb tidak terjangkau, cukup catat errornya:
        // pengirim tidak perlu mengulang, dan klien memuat ulang data tiap 60 detik.
        try {
            foreach ($changed->groupBy('floor_id') as $floorId => $slots) {
                foreach ($slots->chunk(self::SLOTS_PER_MESSAGE) as $chunk) {
                    SlotsChanged::dispatch($floorId, $chunk->map->toPublicArray()->values()->all());
                }
            }

            Floor::withStatusCounts()
                ->whereIn('id', $changed->pluck('floor_id')->unique())
                ->get()
                ->each(fn (Floor $floor) => FloorSummaryUpdated::dispatch($floor->summary()));
        } catch (BroadcastException $e) {
            report($e);
        }
    }
}
