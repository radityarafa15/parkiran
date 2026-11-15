<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** Pengelola: semua halaman. */
    public const ADMIN = 'admin';

    /** Petugas gerbang: hanya halaman scan QR. */
    public const GATE = 'petugas';

    /** Security: hanya daftar laporan pengunjung. */
    public const SECURITY = 'security';

    public const ROLES = [self::ADMIN, self::GATE, self::SECURITY];

    public const ROLE_LABELS = [self::ADMIN => 'Admin', self::GATE => 'Petugas gerbang', self::SECURITY => 'Security'];

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role ?? self::ADMIN, $roles, true);
    }

    /**
     * Halaman pertama setelah login, sesuai peran.
     */
    public function homeUrl(): string
    {
        return match ($this->role) {
            self::GATE => route('admin.gate'),
            self::SECURITY => route('admin.security'),
            default => route('admin.dashboard'),
        };
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
