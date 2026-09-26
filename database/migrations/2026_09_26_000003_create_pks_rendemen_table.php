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
        Schema::create('pks_rendemen', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('no_tiket_timbangan')->unique();
            $table->foreignUuid('kebun_id')->nullable()->constrained('kebun')->nullOnDelete();
            $table->date('tanggal_terima');
            $table->decimal('berat_tbs_terima_kg', 12, 2);
            $table->decimal('rendemen_cpo_persen', 5, 2);
            $table->decimal('rendemen_pk_persen', 5, 2);
            $table->decimal('ffa_persen', 5, 2);
            $table->text('catatan')->nullable();
            $table->json('payload_raw')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pks_rendemen');
    }
};
