<?php

namespace App\Console\Commands;

use App\Models\Slot;
use App\Services\SensorReadingService;
use Illuminate\Console\Command;

class SimulateSensors extends Command
{
    protected $signature = 'parkir:simulate
        {--interval=3 : Detik antar putaran}
        {--once : Jalankan satu putaran saja}
        {--occupancy=0.6 : Perkiraan bagian slot yang terisi (0 sampai 1)}';

    protected $description = 'Simulasikan sensor ultrasonik: semua sensor melapor, sebagian slot berganti status';

    public function handle(SensorReadingService $service): int
    {
        $occupancy = max(0.0, min(1.0, (float) $this->option('occupancy')));
        $interval = max(1, (int) $this->option('interval'));
        $threshold = config('parkir.occupied_threshold_cm');

        $this->info('Simulator sensor berjalan. Tekan Ctrl+C untuk berhenti.');

        do {
            $changed = 0;

            foreach (Slot::all() as $slot) {
                $occupied = $slot->is_occupied ?? (mt_rand() / mt_getrandmax() < $occupancy);

                // Mobil datang atau pergi: sekitar 5% slot berganti status tiap putaran,
                // condong ke tingkat okupansi yang diminta.
                if (mt_rand(1, 100) <= 5) {
                    $occupied = mt_rand() / mt_getrandmax() < $occupancy;
                }

                // Plafon sekitar 260 cm; atap mobil membuat jarak terukur jauh di bawah ambang.
                $distance = $occupied ? mt_rand(60, $threshold - 20) : mt_rand($threshold + 60, 270);

                if ($slot->is_occupied !== $occupied) {
                    $changed++;
                }

                $service->record($slot->sensor_id, $distance);
            }

            $this->line(now()->format('H:i:s')." · {$changed} slot berubah status");

            if (! $this->option('once')) {
                sleep($interval);
            }
        } while (! $this->option('once'));

        return self::SUCCESS;
    }
}
