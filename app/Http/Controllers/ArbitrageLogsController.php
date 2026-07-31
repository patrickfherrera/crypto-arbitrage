<?php

namespace App\Http\Controllers;

use App\Models\ArbitrageLog;
use Illuminate\Support\Facades\Request;
use Inertia\Inertia;
use Inertia\Response;

class ArbitrageLogsController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('ArbitrageLogs/Index', [
            'filters' => Request::all('search', 'profitable'),
            'arbitrageLogs' => ArbitrageLog::with([
                    'coin_arbitrage',
                    'coin_arbitrage.coin_one',
                    'coin_arbitrage.coin_two',
                    'coin_arbitrage.coin_three'])
                ->when(Request::filled('profitable'), function ($query) {
                    $query->where('status', Request::input('profitable'));
                })
                ->orderBy('created_at', 'DESC')
                ->paginate(50)
                ->withQueryString()
                ->through(fn ($arbitrageLog) => [
                    'created_at' => $arbitrageLog->created_at->toIso8601String(),
                    'coin_one_name' => $arbitrageLog->coin_arbitrage->coin_one->symbol,
                    'coin_two_name' => $arbitrageLog->coin_arbitrage->coin_two->symbol,
                    'coin_three_name' => $arbitrageLog->coin_arbitrage->coin_three->symbol,
                    'capital' => number_format($arbitrageLog->capital, 2),
                    'profit' => number_format($arbitrageLog->profit, 6),
                    'final_amount' => number_format($arbitrageLog->final_amount, 6),
                    'status' => str_replace('_', ' ', $arbitrageLog->status),
                    'profit_pct' => number_format((float) $arbitrageLog->profit_pct, 4),
                    'direction' => $arbitrageLog->direction,
                    'quote_age_ms' => $arbitrageLog->quote_age_ms,
                ]),
        ]);
    }

}
