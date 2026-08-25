<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_trade_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coin_arbitrage_id')->nullable()->constrained('coin_arbitrages')->nullOnDelete();
            $table->string('source', 32); // live_trade | daemon
            $table->string('direction', 16)->nullable();
            $table->decimal('capital', 20, 8)->nullable();
            $table->decimal('usdt_before', 20, 8);
            $table->decimal('usdt_after', 20, 8)->nullable();
            $table->decimal('usdt_delta', 20, 8)->nullable();
            $table->decimal('usdt_delta_pct', 12, 6)->nullable(); // vs capital
            $table->decimal('sim_profit_pct', 12, 6)->nullable();
            $table->unsignedInteger('quote_age_ms')->nullable();
            $table->string('status', 32); // completed | partial | failed
            $table->text('error')->nullable();
            $table->json('balances_after')->nullable();
            $table->timestamps();

            $table->index(['created_at']);
            $table->index(['coin_arbitrage_id', 'created_at']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_trade_logs');
    }
};
