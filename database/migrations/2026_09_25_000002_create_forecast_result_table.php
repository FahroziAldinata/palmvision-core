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
        Schema::create('forecast_result', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('blok_id')->constrained('blok')->cascadeOnDelete();
            $table->date('periode');
            $table->decimal('nilai_kg', 12, 2);
            $table->decimal('interval_bawah', 12, 2);
            $table->decimal('interval_atas', 12, 2);
            $table->decimal('mape_model', 6, 4);
            $table->string('versi_model');
            $table->timestamps();

            $table->index(['blok_id', 'periode']);
            $table->index(['blok_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('forecast_result');
    }
};
