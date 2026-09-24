<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('kebun', function (Blueprint $table) {
            $table->unsignedInteger('siklus_rotasi_hari')->default(10)->after('koordinat_pusat');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kebun', function (Blueprint $table) {
            $table->dropColumn('siklus_rotasi_hari');
        });
    }
};
