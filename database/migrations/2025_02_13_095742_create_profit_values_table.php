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
        if (!Schema::hasTable('profit_values')) {

            Schema::create('profit_values', function (Blueprint $table) {
                $table->id();
                $table->decimal('value', 64, 16)->nullable();
                $table->timestamps();
            });

        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('profit_values')) {
            Schema::drop('profit_values');
        }
    }
};
