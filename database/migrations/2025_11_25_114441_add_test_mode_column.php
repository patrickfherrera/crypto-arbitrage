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
            $table->boolean('test_mode')->default(1)->after('capital');
            $table->dropColumn('quantity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('coin_arbitrages', function (Blueprint $table) {
            $table->dropColumn('test_mode');
            $table->integer('quantity')->default(0)->nullable()->after('capital');
        });
    }
};
