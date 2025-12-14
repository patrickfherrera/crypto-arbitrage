<?php

namespace App\Http\Controllers;

use App\Models\Coin;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Request;
use Inertia\Inertia;
use Inertia\Response;

class CoinsController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Coins/Index', [
            'filters' => Request::all('search', 'trashed'),
            'coins' => Coin::orderBy('symbol')
                ->filter(Request::only('search', 'trashed'))
                ->paginate(10)
                ->withQueryString()
                ->through(fn ($coin) => [
                    'id' => $coin->id,
                    'symbol' => $coin->symbol,
                    'base_asset' => $coin->base_asset,
                    'quote_asset' => $coin->quote_asset,
                    'deleted_at' => $coin->deleted_at,
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
        $coin->update(
            Request::validate([
                'base_asset' => ['required'],
                'quote_asset' => ['required'],
                'transfer_fee' => ['required', 'numeric'],
                'enabled' => ['boolean']
            ])
        );

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
