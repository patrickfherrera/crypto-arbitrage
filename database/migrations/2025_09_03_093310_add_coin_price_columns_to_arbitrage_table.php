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
            $table->string('coin_one_price')->after('coin_one_to_asset')->nullable();
            $table->string('coin_two_price')->after('coin_two_to_asset')->nullable();
            $table->string('coin_three_price')->after('coin_three_to_asset')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('coin_arbitrages', function (Blueprint $table) {
            $table->dropColumn('coin_one_price');
            $table->dropColumn('coin_two_price');
            $table->dropColumn('coin_three_price');
        });
    }
};
