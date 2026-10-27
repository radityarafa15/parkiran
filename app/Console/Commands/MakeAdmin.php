<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakeAdmin extends Command
{
    protected $signature = 'parkir:make-admin {email} {--name=Admin Parkir} {--generate : Buat kata sandi acak tanpa bertanya}';

    protected $description = 'Buat akun admin (atau atur ulang kata sandinya) untuk dashboard sensor';

    public function handle(): int
    {
        $email = $this->argument('email');

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Email tidak valid.');

            return self::FAILURE;
        }

        $password = $this->input->isInteractive() && ! $this->option('generate')
            ? $this->secret('Kata sandi (kosongkan untuk dibuatkan otomatis)')
            : null;
        $generated = blank($password);

        if ($generated) {
            $password = Str::password(16);
        }

        User::updateOrCreate(
            ['email' => $email],
            ['name' => $this->option('name'), 'password' => $password],
        );

        $this->info("Akun admin {$email} siap. Masuk di /admin/login.");

        if ($generated) {
            $this->line("Kata sandi: {$password}");
        }

        return self::SUCCESS;
    }
}
