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
        Schema::create('pemupukan', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('blok_id')->constrained('blok')->cascadeOnDelete();
            $table->foreignId('dicatat_oleh')->constrained('users')->cascadeOnDelete();
            $table->date('tanggal_aplikasi');
            $table->string('jenis_pupuk');
            $table->decimal('dosis_kg_per_pokok', 8, 2);
            $table->integer('jumlah_pokok_dipupuk');
            $table->decimal('total_kg_terpakai', 12, 2);
            $table->string('cara_aplikasi');
            $table->text('catatan')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['blok_id', 'tanggal_aplikasi']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pemupukan');
    }
};
