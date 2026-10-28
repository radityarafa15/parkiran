<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Slot ditahan sampai waktu ini (reservasi belum dibayar, menunggu masuk,
        // atau baru masuk gerbang). Disimpan di slot supaya hitungan status cepat.
        Schema::table('slots', function (Blueprint $table) {
            $table->timestamp('reserved_until')->nullable();
        });

        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->string('code', 16)->unique();
            $table->foreignId('slot_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('plate_number', 16);
            $table->unsignedInteger('fee');
            $table->string('status', 16)->index();
            $table->timestamp('pay_before');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('checked_out_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');

        Schema::table('slots', function (Blueprint $table) {
            $table->dropColumn('reserved_until');
        });
    }
};
