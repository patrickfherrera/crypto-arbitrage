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
        if (!Schema::hasTable('usdt_values')) {

            Schema::create('usdt_values', function (Blueprint $table) {
                $table->id();
                $table->decimal('value')->nullable();
                $table->timestamps();
            });

        }

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('usdt_values')) {
            Schema::drop('usdt_values');
        }
    }
};
