<?php

namespace App\Http\Controllers;

use App\Models\Coin;
use App\Models\CoinArbitrage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Request;
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
                    'coin_one' => $coinArbitrage->coin_one,
                    'coin_one_from_asset' => $coinArbitrage->coin_one_from_asset,
                    'coin_two' => $coinArbitrage->coin_two,
                    'coin_two_from_asset' => $coinArbitrage->coin_two_from_asset,
                    'coin_three' => $coinArbitrage->coin_three,
                    'coin_three_from_asset' => $coinArbitrage->coin_three_from_asset,
                ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Coins/Create');
    }

    public function store(): RedirectResponse
    {
        Coin::create(
            Request::validate([
                'base_asset' => ['required'],
                'quote_asset' => ['required'],
                'transfer_fee' => ['required', 'numeric'],
                'enabled' => ['boolean']
            ])
        );

        return Redirect::route('coins')->with('success', 'Coin created.');
    }

    public function edit(Coin $coin): Response
    {
        return Inertia::render('Coins/Edit', [
            'coin' => [
                'id' => $coin->id,
                'base_asset' => $coin->base_asset,
                'quote_asset' => $coin->quote_asset,
                'transfer_fee' => $coin->transfer_fee,
                'enabled' => $coin->enabled,
                'deleted_at' => $coin->deleted_at
            ],
        ]);
    }

    public function update(Coin $coin): RedirectResponse
    {
        $data = Request::validate([
            'base_asset'   => ['required'],
            'quote_asset'  => ['required'],
            'transfer_fee' => ['required', 'numeric'],
            'enabled'      => ['boolean'],
        ]);

        $data['symbol'] = $data['base_asset'] . $data['quote_asset'];

        $coin->update($data);

        return Redirect::back()->with('success', 'Coin updated.');
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
