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
        Schema::table('coin_arbitrages', function (Blueprint $table) {
            $table->decimal('capital', 64, 16)->after('profit')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('coin_arbitrages', function (Blueprint $table) {
            $table->dropColumn('capital');
        });
    }
};
