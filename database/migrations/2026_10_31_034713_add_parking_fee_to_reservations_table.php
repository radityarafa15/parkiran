<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Biaya parkir yang ditagih saat keluar gerbang; disimpan supaya
        // perubahan tarif nanti tidak mengubah riwayat.
        Schema::table('reservations', function (Blueprint $table) {
            $table->unsignedInteger('parking_fee')->nullable()->after('checked_out_at');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn('parking_fee');
        });
    }
};
