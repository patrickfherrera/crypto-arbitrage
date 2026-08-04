<?php

namespace App\Http\Controllers;

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
        return Inertia::render('Arbitrages/Index', [
            'filters' => Request::all('search', 'trashed'),
            'arbitrages' => CoinArbitrage::with([
                    'coin_one',
                    'coin_two',
                    'coin_three',
                ])
                ->paginate(50)
                ->withQueryString()
                ->through(fn ($coinArbitrage) => [
                    'id' => $coinArbitrage->id,
                    'enabled' => (bool) $coinArbitrage->enabled,
                    'coin_one' => $coinArbitrage->coin_one,
                    'coin_one_price' => $coinArbitrage->coin_one_price,
                    'coin_two' => $coinArbitrage->coin_two,
                    'coin_two_price' => $coinArbitrage->coin_two_price,
                    'coin_three' => $coinArbitrage->coin_three,
                    'coin_three_price' => $coinArbitrage->coin_three_price,
                ]),
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
