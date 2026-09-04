<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_trade_logs', function (Blueprint $table) {
            $table->decimal('equity_before', 20, 8)->nullable()->after('usdt_delta_pct');
            $table->decimal('equity_after', 20, 8)->nullable()->after('equity_before');
            $table->decimal('equity_delta', 20, 8)->nullable()->after('equity_after');
            $table->decimal('equity_delta_pct', 12, 6)->nullable()->after('equity_delta');
        });
    }

    public function down(): void
    {
        Schema::table('live_trade_logs', function (Blueprint $table) {
            $table->dropColumn([
                'equity_before',
                'equity_after',
                'equity_delta',
                'equity_delta_pct',
            ]);
        });
    }
};
