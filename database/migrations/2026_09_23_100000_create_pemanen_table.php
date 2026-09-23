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
        Schema::create('pemanen', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('afdeling_id')->constrained('afdeling')->cascadeOnDelete();
            $table->string('nama');
            $table->string('kode_pemanen');
            $table->string('status')->default('aktif'); // aktif, nonaktif
            $table->timestamps();

            $table->unique(['afdeling_id', 'kode_pemanen'], 'pemanen_afdeling_kode_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pemanen');
    }
};
