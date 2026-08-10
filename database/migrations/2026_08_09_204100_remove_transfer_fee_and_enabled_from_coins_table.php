<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('coins', 'transfer_fee')) {
            Schema::table('coins', function (Blueprint $table) {
                $table->dropColumn('transfer_fee');
            });
        }

        if (Schema::hasColumn('coins', 'enabled')) {
            Schema::table('coins', function (Blueprint $table) {
                $table->dropColumn('enabled');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('coins', 'transfer_fee')) {
            Schema::table('coins', function (Blueprint $table) {
                $table->decimal('transfer_fee', 64, 16)->nullable()->after('symbol');
            });
        }

        if (! Schema::hasColumn('coins', 'enabled')) {
            Schema::table('coins', function (Blueprint $table) {
                $table->boolean('enabled')->default(0)->nullable();
            });
        }
    }
};
