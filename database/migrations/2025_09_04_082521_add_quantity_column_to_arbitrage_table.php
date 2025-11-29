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
            if (Schema::hasColumn('coin_arbitrages', 'quantity')) {
                $table->dropColumn('quantity');
            }
            $table->integer('quantity')->default(0)->nullable()->after('capital');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('coin_arbitrages', function (Blueprint $table) {
            $table->dropColumn('quantity');
        });
    }
};
