<?php

namespace App\Console\Commands;

use App\Models\Reservation;
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
            $readings = [];
            // Mobil yang sudah masuk gerbang lewat reservasi akan tiba di slotnya.
            $arriving = Reservation::where('status', Reservation::PARKED)->pluck('slot_id')->flip();

            foreach (Slot::select(['id', 'sensor_id', 'is_occupied', 'reserved_until'])->cursor() as $slot) {
                $occupied = $slot->is_occupied ?? (mt_rand() / mt_getrandmax() < $occupancy);

                if ($arriving->has($slot->id) && $slot->isReserved()) {
                    $occupied = mt_rand(1, 100) <= 30 ? true : $occupied;
                } elseif ($slot->isReserved()) {
                    // Slot yang ditahan reservasi tidak diisi mobil lain.
                    $occupied = false;
                } elseif (mt_rand(1, 100) <= 2) {
                    // Mobil datang atau pergi: sekitar 2% slot berganti status tiap putaran,
                    // condong ke tingkat okupansi yang diminta.
                    $occupied = mt_rand() / mt_getrandmax() < $occupancy;
                }

                // Plafon sekitar 260 cm; atap mobil membuat jarak terukur jauh di bawah ambang.
                $readings[] = [
                    'sensor_id' => $slot->sensor_id,
                    'distance_cm' => $occupied ? mt_rand(60, $threshold - 20) : mt_rand($threshold + 60, 270),
                ];
            }

            // Kirim per 500 seperti gateway sungguhan.
            $changed = 0;
            foreach (array_chunk($readings, 500) as $chunk) {
                $changed += $service->recordMany($chunk)['changed'];
            }

            $this->line(now()->format('H:i:s').' · '.count($readings)." sensor melapor, {$changed} slot berubah status");

            if (! $this->option('once')) {
                sleep($interval);
            }
        } while (! $this->option('once'));

        return self::SUCCESS;
    }
}
