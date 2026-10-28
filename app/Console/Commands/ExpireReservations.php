<?php

namespace App\Console\Commands;

use App\Services\ReservationService;
use Illuminate\Console\Command;

class ExpireReservations extends Command
{
    protected $signature = 'parkir:expire-reservations';

    protected $description = 'Hanguskan reservasi yang lewat batas waktu dan lepaskan slotnya';

    public function handle(ReservationService $service): int
    {
        $expired = $service->expireOverdue();

        if ($expired > 0) {
            $this->info("{$expired} reservasi hangus.");
        }

        return self::SUCCESS;
    }
}
