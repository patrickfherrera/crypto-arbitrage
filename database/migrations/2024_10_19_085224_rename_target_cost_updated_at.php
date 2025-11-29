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
            $table->renameColumn('target_cost_updated_at', 'last_sold_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('coins', function (Blueprint $table) {
            $table->renameColumn('last_sold_at', 'target_cost_updated_at');
        });
    }
};
