<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class InstallParkir extends Command
{
    protected $signature = 'parkir:install
        {--admin=admin@parkiran.test : Email akun admin pertama}
        {--fresh : Hapus semua data lalu isi ulang 6.995 slot}';

    protected $description = 'Siapkan aplikasi untuk dijalankan di komputer sendiri: kunci, database, data slot, akun admin';

    public function handle(): int
    {
        $this->fillEnv([
            'PARKIR_SENSOR_TOKEN' => Str::random(32),
            'REVERB_APP_ID' => 'parkiran',
            'REVERB_APP_KEY' => Str::lower(Str::random(20)),
            'REVERB_APP_SECRET' => Str::lower(Str::random(32)),
        ]);

        if (config('database.default') === 'sqlite') {
            $path = config('database.connections.sqlite.database');

            if ($path !== ':memory:' && ! File::exists($path)) {
                File::put($path, '');
            }
        }

        $this->call($this->option('fresh') ? 'migrate:fresh' : 'migrate', ['--force' => true]);
        $this->call('db:seed', ['--force' => true]);

        if (User::doesntExist()) {
            $this->call('parkir:make-admin', ['email' => $this->option('admin'), '--generate' => true]);
        }

        $this->newLine();
        $this->info('Siap. Jalankan "composer dev", lalu buka http://localhost:8000 (admin: http://localhost:8000/admin).');

        return self::SUCCESS;
    }

    /**
     * Isi nilai .env yang masih kosong; nilai yang sudah ada tidak diubah.
     *
     * @param  array<string, string>  $defaults
     */
    private function fillEnv(array $defaults): void
    {
        $path = $this->laravel->environmentFilePath();

        if (! File::exists($path)) {
            File::copy(base_path('.env.example'), $path);
        }

        $env = File::get($path);

        foreach ($defaults as $key => $value) {
            $pattern = '/^'.preg_quote($key, '/').'=(.*)$/m';

            if (preg_match($pattern, $env, $match)) {
                if (trim($match[1], " \t\"'") === '') {
                    $env = preg_replace($pattern, "{$key}={$value}", $env);
                }
            } else {
                $env = rtrim($env)."\n{$key}={$value}\n";
            }
        }

        File::put($path, $env);
    }
}
