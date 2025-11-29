<?php

use App\Models\CoinArbitrage;
use Illuminate\Support\Facades\Schedule;

// Reset demo environment daily at midnight
Schedule::command('migrate:fresh --seed --force')->dailyAt('00:00');

CoinArbitrage::where('enabled', '=', 1)->each(function ($coin_arbitrage) {
//            Schedule::command('app:arbitrage-experimental --coin_arbitrage_id=' . $coin_arbitrage->id)->everyMinute()->withoutOverlapping();
    Schedule::command('arbitrage:run --interval=5 --coin_arbitrage_id=' . $coin_arbitrage->id)->everyMinute()->withoutOverlapping();
});
