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
        Schema::table('produksi_harian', function (Blueprint $table) {
            $table->timestamp('device_time')->nullable()->after('client_uuid');
            $table->boolean('perlu_tinjauan_waktu')->default(false)->after('device_time');
            $table->index('client_uuid');
        });

        Schema::table('taksasi', function (Blueprint $table) {
            $table->string('sumber')->default('web')->after('catatan');
            $table->uuid('client_uuid')->nullable()->after('sumber');
            $table->timestamp('device_time')->nullable()->after('client_uuid');
            $table->boolean('perlu_tinjauan_waktu')->default(false)->after('device_time');
            $table->index('client_uuid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('taksasi', function (Blueprint $table) {
            $table->dropIndex(['client_uuid']);
            $table->dropColumn(['sumber', 'client_uuid', 'device_time', 'perlu_tinjauan_waktu']);
        });

        Schema::table('produksi_harian', function (Blueprint $table) {
            $table->dropIndex(['client_uuid']);
            $table->dropColumn(['device_time', 'perlu_tinjauan_waktu']);
        });
    }
};
