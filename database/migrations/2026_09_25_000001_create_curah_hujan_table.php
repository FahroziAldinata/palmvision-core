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
        Schema::create('curah_hujan', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('kebun_id')->constrained('kebun')->cascadeOnDelete();
            $table->date('tanggal');
            $table->decimal('curah_hujan_mm', 8, 2);
            $table->string('sumber')->default('open-meteo');
            $table->timestamps();

            $table->unique(['kebun_id', 'tanggal']);
            $table->index(['kebun_id', 'tanggal']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('curah_hujan');
    }
};
