<?php

namespace App\Http\Controllers;

use App\Models\ArbitrageLog;
use App\Models\Coin;
use App\Models\CoinArbitrage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class ArbitrageController extends Controller
{
    public function index(): Response
    {
        $enabled = Request::input('enabled', 'all');

        $stats = ArbitrageLog::query()
            ->whereNotNull('profit_pct')
            ->selectRaw('coin_arbitrage_id, COUNT(*) as total, SUM(CASE WHEN profit_pct > 0 THEN 1 ELSE 0 END) as wins, MAX(profit_pct) as best_pct, AVG(profit_pct) as mean_pct')
            ->groupBy('coin_arbitrage_id')
            ->get()
            ->keyBy('coin_arbitrage_id');

        return Inertia::render('Arbitrages/Index', [
            'filters' => [
                'search' => Request::input('search'),
                'enabled' => $enabled,
            ],
            'arbitrages' => CoinArbitrage::with([
                    'coin_one',
                    'coin_two',
                    'coin_three',
                ])
                ->when($enabled === 'enabled', fn ($q) => $q->where('enabled', true))
                ->when($enabled === 'disabled', fn ($q) => $q->where('enabled', false))
                ->orderByDesc('enabled')
                ->orderByDesc('created_at')
                ->paginate(50)
                ->withQueryString()
                ->through(function ($coinArbitrage) use ($stats) {
                    $row = $stats->get($coinArbitrage->id);
                    $total = (int) ($row->total ?? 0);
                    $wins = (int) ($row->wins ?? 0);

                    return [
                        'id' => $coinArbitrage->id,
                        'enabled' => (bool) $coinArbitrage->enabled,
                        'test_mode' => (bool) $coinArbitrage->test_mode,
                        'capital' => $coinArbitrage->capital !== null ? (float) $coinArbitrage->capital : null,
                        'created_at' => $coinArbitrage->created_at?->toIso8601String(),
                        'coin_one' => $coinArbitrage->coin_one,
                        'coin_one_price' => $coinArbitrage->coin_one_price,
                        'coin_two' => $coinArbitrage->coin_two,
                        'coin_two_price' => $coinArbitrage->coin_two_price,
                        'coin_three' => $coinArbitrage->coin_three,
                        'coin_three_price' => $coinArbitrage->coin_three_price,
                        'log_total' => $total,
                        'wins' => $wins,
                        'win_rate' => $total > 0 ? ($wins / $total) * 100 : null,
                        'best_pct' => $row?->best_pct !== null ? (float) $row->best_pct : null,
                        'mean_pct' => $row?->mean_pct !== null ? (float) $row->mean_pct : null,
                    ];
                }),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Arbitrages/Create', [
            'coins' => Coin::all()
        ]);
    }

    public function store(): RedirectResponse
    {
        CoinArbitrage::create(
            Request::validate([
                'coin_one_id' => ['required'],
                'coin_one_price' => ['required'],
                'coin_two_id' => ['required'],
                'coin_two_price' => ['required'],
                'coin_three_id' => ['required'],
                'coin_three_price' => ['required'],
                'profit' => ['required', 'numeric'],
                'capital' => ['required', 'numeric'],
                'test_mode' => ['required', 'boolean'],
                'enabled' => ['required', 'boolean']
            ])
        );

        Cache::put('binance.feed.reload', true);

        return Redirect::route('coins')->with('success', 'Coin Arbitrage created.');
    }

    public function edit(CoinArbitrage $arbitrage): Response
    {
        return Inertia::render('Arbitrages/Edit', [
            'coins' => Coin::all(),
            'coinArbitrage' => [
                'id' => $arbitrage->id,
                'coin_one_id' => $arbitrage->coin_one_id,
                'coin_one_price' => $arbitrage->coin_one_price,
                'coin_two_id' => $arbitrage->coin_two_id,
                'coin_two_price' => $arbitrage->coin_two_price,
                'coin_three_id' => $arbitrage->coin_three_id,
                'coin_three_price' => $arbitrage->coin_three_price,
                'profit' => $arbitrage->profit,
                'capital' => $arbitrage->capital,
                'test_mode' => $arbitrage->test_mode,
                'enabled' => $arbitrage->enabled,
            ],
        ]);
    }

    public function update(CoinArbitrage $arbitrage): RedirectResponse
    {
        $arbitrage->update(
            Request::validate([
                'coin_one_id' => ['required'],
                'coin_one_price' => ['required'],
                'coin_two_id' => ['required'],
                'coin_two_price' => ['required'],
                'coin_three_id' => ['required'],
                'coin_three_price' => ['required'],
                'profit' => ['required', 'numeric'],
                'capital' => ['required', 'numeric'],
                'test_mode' => ['required', 'boolean'],
                'enabled' => ['required', 'boolean'],
            ])
        );
    
        Cache::put('binance.feed.reload', true);

        return Redirect::route('arbitrages')->with('success', 'Arbitrage updated.');
    }

    public function destroy(Coin $coin): RedirectResponse
    {
        $coin->delete();

        return Redirect::back()->with('success', '');
    }

    public function restore(Coin $coin): RedirectResponse
    {
        $coin->restore();

        return Redirect::back()->with('success', 'Coin restored.');
    }
}
