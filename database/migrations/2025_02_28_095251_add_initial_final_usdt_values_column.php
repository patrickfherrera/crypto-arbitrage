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
            $table->decimal('initial_usdt_value', 64, 16)->after('value')->nullable();
            $table->decimal('final_usdt_value', 64, 16)->after('initial_usdt_value')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('profit_values', function (Blueprint $table) {
            $table->dropColumn('initial_usdt_value');
            $table->dropColumn('final_usdt_value');
        });
    }
};
