<?php

use App\Models\CoinArbitrage;
use Illuminate\Support\Facades\Schedule;

CoinArbitrage::where('enabled', '=', 1)->each(function ($coin_arbitrage) {
//            Schedule::command('app:arbitrage-experimental --coin_arbitrage_id=' . $coin_arbitrage->id)->everyMinute()->withoutOverlapping();
    Schedule::command('arbitrage:run --interval=5 --coin_arbitrage_id=' . $coin_arbitrage->id)->everyMinute()->withoutOverlapping();
});
