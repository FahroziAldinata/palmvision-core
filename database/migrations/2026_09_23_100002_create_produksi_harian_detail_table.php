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
        Schema::create('produksi_harian_detail', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('produksi_harian_id')->constrained('produksi_harian')->cascadeOnDelete();
            $table->foreignUuid('pemanen_id')->constrained('pemanen')->cascadeOnDelete();
            $table->integer('jumlah_janjang');
            $table->decimal('berat_kg', 10, 2);
            $table->timestamps();

            $table->unique(['produksi_harian_id', 'pemanen_id'], 'produksi_detail_header_pemanen_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('produksi_harian_detail');
    }
};
