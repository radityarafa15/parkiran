<?php

namespace App\Console\Commands;

use App\Events\FloorSummaryUpdated;
use App\Models\Floor;
use Illuminate\Console\Command;

class BroadcastSummaries extends Command
{
    protected $signature = 'parkir:broadcast-summaries';

    protected $description = 'Siarkan ulang ringkasan tiap lantai (menangkap sensor yang berhenti melapor)';

    public function handle(): int
    {
        Floor::withStatusCounts()->get()->each(fn (Floor $floor) => FloorSummaryUpdated::dispatch($floor->summary()));

        return self::SUCCESS;
    }
}
