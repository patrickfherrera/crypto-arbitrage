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
        Schema::create('arbitrage_logs', function (Blueprint $table) {
            $table->id();
            $table->decimal('capital', 16, 8);
            $table->decimal('final_amount', 16, 8);
            $table->decimal('profit', 16, 8);
            $table->string('status'); // PROFITABLE / NOT_PROFITABLE
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('arbitrage_logs');
    }
};
