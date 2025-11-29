<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class Coins extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('coins')) {
            Schema::create('coins', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('symbol');
                $table->double('value', 8, 3)->nullable();
                $table->decimal('target_value', 64, 16)->nullable();
                $table->decimal('target_cost', 64, 16)->nullable();
                $table->timestamp('target_cost_updated_at')->nullable();
                $table->decimal('trade_fee', 64, 16)->nullable();
                $table->decimal('profit_loss', 64, 16)->default(null)->nullable();
                $table->boolean('enabled')->default(0);
                $table->boolean('traded')->default(0);
                $table->double('quantity', 15, 8)->default(1)->nullable();
                $table->decimal('cost', 64, 16)->nullable();
                $table->decimal('up_cost', 64, 16)->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasTable('coins')) {
            Schema::drop('coins');
        }
    }
}
