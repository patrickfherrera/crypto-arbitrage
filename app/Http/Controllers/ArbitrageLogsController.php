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
                    'created_at' => Carbon::parse($arbitrageLog->created_at)
                        ->timezone('Asia/Manila')
                        ->format('m/d/Y g:iA'),
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

    public function create(): Response
    {
        return Inertia::render('Contacts/Create', [
            'organizations' => Auth::user()->account
                ->organizations()
                ->orderBy('name')
                ->get()
                ->map
                ->only('id', 'name'),
        ]);
    }

    public function store(): RedirectResponse
    {
        Auth::user()->account->contacts()->create(
            Request::validate([
                'first_name' => ['required', 'max:50'],
                'last_name' => ['required', 'max:50'],
                'organization_id' => ['nullable', Rule::exists('organizations', 'id')->where(function ($query) {
                    $query->where('account_id', Auth::user()->account_id);
                })],
                'email' => ['nullable', 'max:50', 'email'],
                'phone' => ['nullable', 'max:50'],
                'address' => ['nullable', 'max:150'],
                'city' => ['nullable', 'max:50'],
                'region' => ['nullable', 'max:50'],
                'country' => ['nullable', 'max:2'],
                'postal_code' => ['nullable', 'max:25'],
            ])
        );

        return Redirect::route('contacts')->with('success', 'Contact created.');
    }

    public function edit(Contact $contact): Response
    {
        return Inertia::render('Contacts/Edit', [
            'contact' => [
                'id' => $contact->id,
                'first_name' => $contact->first_name,
                'last_name' => $contact->last_name,
                'organization_id' => $contact->organization_id,
                'email' => $contact->email,
                'phone' => $contact->phone,
                'address' => $contact->address,
                'city' => $contact->city,
                'region' => $contact->region,
                'country' => $contact->country,
                'postal_code' => $contact->postal_code,
                'deleted_at' => $contact->deleted_at,
            ],
            'organizations' => Auth::user()->account->organizations()
                ->orderBy('name')
                ->get()
                ->map
                ->only('id', 'name'),
        ]);
    }

    public function update(Contact $contact): RedirectResponse
    {
        $contact->update(
            Request::validate([
                'first_name' => ['required', 'max:50'],
                'last_name' => ['required', 'max:50'],
                'organization_id' => [
                    'nullable',
                    Rule::exists('organizations', 'id')->where(fn ($query) => $query->where('account_id', Auth::user()->account_id)),
                ],
                'email' => ['nullable', 'max:50', 'email'],
                'phone' => ['nullable', 'max:50'],
                'address' => ['nullable', 'max:150'],
                'city' => ['nullable', 'max:50'],
                'region' => ['nullable', 'max:50'],
                'country' => ['nullable', 'max:2'],
                'postal_code' => ['nullable', 'max:25'],
            ])
        );

        return Redirect::back()->with('success', 'Contact updated.');
    }

    public function destroy(Contact $contact): RedirectResponse
    {
        $contact->delete();

        return Redirect::back()->with('success', 'Contact deleted.');
    }

    public function restore(Contact $contact): RedirectResponse
    {
        $contact->restore();

        return Redirect::back()->with('success', 'Contact restored.');
    }
}
