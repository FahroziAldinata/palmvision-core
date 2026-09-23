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
        Schema::create('poligon_blok', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('blok_id')->constrained('blok')->cascadeOnDelete();
            $table->geometry('poligon', subtype: 'polygon', srid: 4326);
            $table->integer('versi');
            $table->date('diperbarui_pada');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('poligon_blok');
    }
};
