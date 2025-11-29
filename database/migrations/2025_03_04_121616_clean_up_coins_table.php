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
        if (Schema::hasColumn('coins', 'value')) {
            Schema::table('coins', function (Blueprint $table) {
                $table->dropColumn('value');
            });
        }

        if (Schema::hasColumn('coins', 'target_value')) {
            Schema::table('coins', function (Blueprint $table) {
                $table->dropColumn('target_value');
            });
        }

        if (Schema::hasColumn('coins', 'target_cost')) {
            Schema::table('coins', function (Blueprint $table) {
                $table->dropColumn('target_cost');
            });
        }

        if (Schema::hasColumn('coins', 'last_sold_at')) {
            Schema::table('coins', function (Blueprint $table) {
                $table->dropColumn('last_sold_at');
            });
        }

        if (Schema::hasColumn('coins', 'traded')) {
            Schema::table('coins', function (Blueprint $table) {
                $table->dropColumn('traded');
            });
        }

        if (Schema::hasColumn('coins', 'cost')) {
            Schema::table('coins', function (Blueprint $table) {
                $table->dropColumn('cost');
            });
        }

        if (Schema::hasColumn('coins', 'up_cost')) {
            Schema::table('coins', function (Blueprint $table) {
                $table->dropColumn('up_cost');
            });
        }

        if (Schema::hasColumn('coins', 'quantity')) {
            Schema::table('coins', function (Blueprint $table) {
                $table->dropColumn('quantity');
            });
        }

        if (!Schema::hasColumn('coins', 'transfer_fee')) {
            Schema::table('coins', function (Blueprint $table) {
                $table->decimal('transfer_fee', 64, 16)->after('symbol')->nullable();
            });
        }

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('coins', function (Blueprint $table) {
            //
        });
    }
};
