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
        Schema::create('kebun', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('grup_id')->constrained('grup_perusahaan')->cascadeOnDelete();
            $table->string('kode_kebun');
            $table->string('nama');
            $table->geometry('koordinat_pusat', subtype: 'point', srid: 4326);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kebun');
    }
};
