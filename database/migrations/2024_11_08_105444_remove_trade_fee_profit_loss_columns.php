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
        Schema::table('coins', function (Blueprint $table) {
            $table->dropColumn('trade_fee');
            $table->dropColumn('profit_loss');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('coins', function (Blueprint $table) {
            $table->decimal('trade_fee', 64, 16)->nullable()->after('last_sold_at');
            $table->decimal('profit_loss', 64, 16)->default(null)->nullable()->after('trade_fee');
        });
    }
};
