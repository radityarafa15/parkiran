<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->timestamp('cancelled_at')->nullable();
            // Biaya admin yang dikembalikan (simulasi): penuh jika batal dalam
            // config('parkir.reservation.refund_minutes') menit setelah bayar.
            $table->unsignedInteger('refund_amount')->default(0);
            // Aturan 1 orang 1 mobil: cari reservasi aktif per plat dengan cepat.
            $table->index(['plate_number', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropIndex(['plate_number', 'status']);
            $table->dropColumn(['cancelled_at', 'refund_amount']);
        });
    }
};
