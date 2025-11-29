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
            $table->string('coin_one_quote_response')->after('coin_arbitrage_profit');
            $table->string('coin_two_quote_response')->after('coin_one_quote_response');
            $table->string('coin_three_quote_response')->after('coin_two_quote_response');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('profit_values', function (Blueprint $table) {
            $table->dropColumn('coin_one_quote_response');
            $table->dropColumn('coin_two_quote_response');
            $table->dropColumn('coin_three_quote_response');
        });
    }
};
