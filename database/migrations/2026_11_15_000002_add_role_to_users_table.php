<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Akun yang sudah ada adalah admin; petugas gerbang dan security dibuat terpisah.
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 16)->default('admin');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
