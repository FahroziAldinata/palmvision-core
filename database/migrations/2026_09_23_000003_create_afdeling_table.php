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
        Schema::create('afdeling', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('kebun_id')->constrained('kebun')->cascadeOnDelete();
            $table->string('kode');
            $table->string('nama');
            $table->foreignId('asisten_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('afdeling');
    }
};
