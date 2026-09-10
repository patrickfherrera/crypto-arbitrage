<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_equity_snapshots', function (Blueprint $table) {
            $table->id();
            $table->decimal('est_total_usdt', 20, 8)->nullable();
            $table->decimal('marked_usdt', 20, 8)->nullable();
            $table->decimal('usdt_cash', 20, 8)->nullable();
            $table->json('skipped')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_equity_snapshots');
    }
};
