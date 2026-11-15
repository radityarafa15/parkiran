<?php

namespace App\Exceptions;

use App\Models\Reservation;
use RuntimeException;

/**
 * Langkah reservasi yang tidak bisa dilakukan; pesannya ditampilkan ke pengguna.
 * $active diisi jika penyebabnya reservasi aktif lain milik plat yang sama.
 */
class ReservationException extends RuntimeException
{
    public function __construct(string $message, public readonly ?Reservation $active = null)
    {
        parent::__construct($message);
    }
}
