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
        Schema::table('users', function (Blueprint $table) {
            $table->foreignUuid('kebun_id')->nullable()->after('password')->constrained('kebun')->nullOnDelete();
            $table->foreignUuid('afdeling_id')->nullable()->after('kebun_id')->constrained('afdeling')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['kebun_id']);
            $table->dropForeign(['afdeling_id']);
            $table->dropColumn(['kebun_id', 'afdeling_id']);
        });
    }
};
