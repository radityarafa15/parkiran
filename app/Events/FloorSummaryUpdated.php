<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Ringkasan terbaru satu lantai, dikirim ke channel publik "floors".
 */
class FloorSummaryUpdated implements ShouldBroadcastNow
{
    use Dispatchable;

    /**
     * @param  array{id: int, name: string, level: int, total: int, available: int, occupied: int, reserved: int, unknown: int}  $summary
     */
    public function __construct(public array $summary) {}

    public function broadcastOn(): array
    {
        return [new Channel('floors'), new Channel("floor.{$this->summary['id']}")];
    }

    public function broadcastAs(): string
    {
        return 'floor.summary';
    }

    public function broadcastWith(): array
    {
        return $this->summary;
    }
}
