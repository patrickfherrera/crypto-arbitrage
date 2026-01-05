<?php

namespace App\Http\Controllers;

use App\Models\ArbitrageLog;
use App\Models\Contact;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Request;
use Illuminate\Validation\Rule;
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
                ->paginate(50)
                ->withQueryString()
                ->through(fn ($arbitrageLog) => [
                    'created_at' => Carbon::parse($arbitrageLog->created_at)->format('m/d/Y g:iA'),
                    'coin_one_name' => $arbitrageLog->coin_arbitrage->coin_one->symbol,
                    'coin_two_name' => $arbitrageLog->coin_arbitrage->coin_two->symbol,
                    'coin_three_name' => $arbitrageLog->coin_arbitrage->coin_three->symbol,
                    'capital' => number_format($arbitrageLog->capital, 2),
                    'profit' => number_format($arbitrageLog->profit, 2),
                    'final_amount' => number_format($arbitrageLog->final_amount, 2),
                    'status' => str_replace('_', ' ', $arbitrageLog->status),
                ]),
        ]);
    }

}
