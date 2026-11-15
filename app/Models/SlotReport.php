<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Laporan pengunjung ke security tentang masalah di slot reservasinya.
 */
class SlotReport extends Model
{
    /** Slot reservasi ditempati mobil lain. */
    public const OCCUPIED = 'occupied';

    /** Slot terhalang barang (troli, cone, dan lain-lain). */
    public const BLOCKED = 'blocked';

    public const OTHER = 'other';

    public const TYPES = [
        self::OCCUPIED => 'Slot ditempati mobil lain',
        self::BLOCKED => 'Slot terhalang barang',
        self::OTHER => 'Lainnya',
    ];

    /** Baru masuk, belum ada security yang menangani. */
    public const NEW = 'new';

    public const HANDLING = 'handling';

    public const DONE = 'done';

    public const STATUSES = [
        self::NEW => 'Baru',
        self::HANDLING => 'Sedang ditangani',
        self::DONE => 'Selesai',
    ];

    protected $fillable = [
        'reservation_id', 'slot_id', 'replacement_slot_id', 'type', 'note',
        'status', 'handled_by', 'handled_at', 'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'handled_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(Slot::class);
    }

    public function replacementSlot(): BelongsTo
    {
        return $this->belongsTo(Slot::class, 'replacement_slot_id');
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'type_label' => $this->typeLabel(),
            'note' => $this->note,
            'status' => $this->status,
            'status_label' => $this->statusLabel(),
            'replacement_slot' => $this->replacementSlot?->code,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
