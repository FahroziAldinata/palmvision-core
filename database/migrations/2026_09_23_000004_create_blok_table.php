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
        Schema::create('blok', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('afdeling_id')->constrained('afdeling')->cascadeOnDelete();
            $table->string('kode_blok');
            $table->decimal('luas_ha', 8, 2);
            $table->date('tanggal_tanam');
            $table->integer('jumlah_pokok');
            $table->string('kategori_tanah');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blok');
    }
};
