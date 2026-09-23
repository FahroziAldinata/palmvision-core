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
        Schema::create('produksi_harian', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('blok_id')->constrained('blok')->cascadeOnDelete();
            $table->foreignId('dicatat_oleh')->constrained('users')->cascadeOnDelete();
            $table->date('tanggal');
            $table->string('status_validasi')->default('menunggu'); // menunggu, disetujui
            $table->text('catatan')->nullable();
            $table->string('sumber')->default('web');
            $table->uuid('client_uuid')->nullable(); // Cadangan untuk PWA/offline sync Tahap 5
            $table->softDeletes();
            $table->timestamps();

            // US-01 AC3: Mencegah entri ganda untuk kombinasi blok, tanggal, dan mandor yang sama di level header
            $table->unique(['blok_id', 'tanggal', 'dicatat_oleh'], 'produksi_harian_blok_tanggal_user_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('produksi_harian');
    }
};
