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
        Schema::create('coin_arbitrages', function (Blueprint $table) {
            $table->id();
            $table->integer('coin_one_id')->default(0)->nullable();
            $table->integer('coin_two_id')->default(0)->nullable();
            $table->integer('coin_three_id')->default(0)->nullable();
            $table->integer('quantity')->default(0)->nullable();
            $table->decimal('profit', 64, 16)->nullable();
            $table->boolean('enabled')->default(0)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('coin_arbitrages')) {
            Schema::drop('coin_arbitrages');
        }
    }
};
