<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Slot di satu lantai yang statusnya berubah, dikirim ke channel lantai itu.
 */
class SlotsChanged implements ShouldBroadcastNow
{
    use Dispatchable;

    /**
     * @param  array<int, array{id: int, code: string, zone: string, status: string}>  $slots
     */
    public function __construct(public int $floorId, public array $slots) {}

    public function broadcastOn(): Channel
    {
        return new Channel("floor.{$this->floorId}");
    }

    public function broadcastAs(): string
    {
        return 'slots.changed';
    }

    public function broadcastWith(): array
    {
        return ['slots' => $this->slots];
    }
}
