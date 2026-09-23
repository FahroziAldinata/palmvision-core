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
        Schema::create('taksasi', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('blok_id')->constrained('blok')->cascadeOnDelete();
            $table->foreignId('dicatat_oleh')->constrained('users')->cascadeOnDelete();
            $table->date('tanggal_taksasi');
            $table->integer('pokok_disampel');
            $table->integer('estimasi_janjang');
            $table->decimal('estimasi_bjr', 8, 2);
            $table->decimal('estimasi_total_kg', 12, 2);
            $table->text('catatan')->nullable();
            $table->softDeletes();
            $table->timestamps();

            // US-03 AC3: Taksasi tersimpan sebagai riwayat per blok (tidak ditimpa)
            $table->index(['blok_id', 'tanggal_taksasi']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('taksasi');
    }
};
