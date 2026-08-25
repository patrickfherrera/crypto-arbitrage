<?php

use App\Models\CoinArbitrage;
use Illuminate\Support\Facades\Schedule;

Schedule::command('app:purge-arbitrage-logs')->dailyAt('00:00');

Schedule::command('arbitrage:scan-triangles --capital=15 --top=10 --seed')
    ->dailyAt('06:00')
    ->withoutOverlapping();
    
CoinArbitrage::where('enabled', '=', 1)->each(function ($coin_arbitrage) {
    // Schedule::command('app:arbitrage-experimental --coin_arbitrage_id=' . $coin_arbitrage->id)->everyMinute()->withoutOverlapping();
    // Schedule::command('arbitrage:run --interval=1 --coin_arbitrage_id=' . $coin_arbitrage->id)->everyMinute()->withoutOverlapping();
});
