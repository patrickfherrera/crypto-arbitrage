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
        Schema::table('profit_values', function (Blueprint $table) {
            $table->integer('coin_arbitrage_id')->after('final_usdt_value')->default(0)->nullable();
            $table->decimal('coin_arbitrage_profit', 64, 16)->after('coin_arbitrage_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('profit_values', function (Blueprint $table) {
            $table->dropColumn('coin_arbitrage_id');
            $table->dropColumn('coin_arbitrage_profit');
        });
    }
};
