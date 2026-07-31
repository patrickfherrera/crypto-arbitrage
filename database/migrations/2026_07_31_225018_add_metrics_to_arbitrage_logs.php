<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('arbitrage_logs', function (Blueprint $table) {
            $table->decimal('profit_pct', 16, 8)->nullable()->after('profit');
            $table->string('direction')->nullable()->after('status'); // forward|reverse
            $table->unsignedInteger('quote_age_ms')->nullable()->after('direction');
        });
    }

    public function down(): void
    {
        Schema::table('arbitrage_logs', function (Blueprint $table) {
            $table->dropColumn(['profit_pct', 'direction', 'quote_age_ms']);
        });
    }
};