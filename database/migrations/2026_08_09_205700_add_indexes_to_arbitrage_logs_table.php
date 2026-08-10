<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('arbitrage_logs', function (Blueprint $table) {
            $table->index(['created_at'], 'arbitrage_logs_created_at_index');
            $table->index(['coin_arbitrage_id', 'created_at'], 'arbitrage_logs_arb_created_index');
            $table->index(['status', 'created_at'], 'arbitrage_logs_status_created_index');
            $table->index(['profit_pct', 'created_at'], 'arbitrage_logs_profit_pct_created_index');
        });
    }

    public function down(): void
    {
        Schema::table('arbitrage_logs', function (Blueprint $table) {
            $table->dropIndex('arbitrage_logs_created_at_index');
            $table->dropIndex('arbitrage_logs_arb_created_index');
            $table->dropIndex('arbitrage_logs_status_created_index');
            $table->dropIndex('arbitrage_logs_profit_pct_created_index');
        });
    }
};
