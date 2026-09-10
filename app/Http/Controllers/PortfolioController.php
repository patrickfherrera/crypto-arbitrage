<?php

namespace App\Http\Controllers;

use App\Models\WalletEquitySnapshot;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Request;
use Inertia\Inertia;
use Inertia\Response;

class PortfolioController extends Controller
{
    public function index(): Response
    {
        $range = Request::input('range', '7d');
        $since = match ($range) {
            '24h' => now()->subDay(),
            '30d' => now()->subDays(30),
            default => now()->subDays(7),
        };
        if (! in_array($range, ['24h', '7d', '30d'], true)) {
            $range = '7d';
        }

        // First visit / empty: take one snapshot so the page isn't blank.
        if (! WalletEquitySnapshot::query()->exists()) {
            Artisan::call('wallet:snapshot-equity');
        }

        $points = WalletEquitySnapshot::query()
            ->where('created_at', '>=', $since)
            ->whereNotNull('est_total_usdt')
            ->orderBy('created_at')
            ->get(['id', 'est_total_usdt', 'marked_usdt', 'usdt_cash', 'created_at']);

        $first = $points->first();
        $last = $points->last();
        $delta = ($first && $last)
            ? (float) $last->est_total_usdt - (float) $first->est_total_usdt
            : null;
        $deltaPct = ($first && $last && (float) $first->est_total_usdt > 0)
            ? ($delta / (float) $first->est_total_usdt) * 100
            : null;

        return Inertia::render('Portfolio/Index', [
            'range' => $range,
            'summary' => [
                'latest' => $last?->est_total_usdt,
                'marked' => $last?->marked_usdt,
                'usdt_cash' => $last?->usdt_cash,
                'delta' => $delta,
                'delta_pct' => $deltaPct,
                'points' => $points->count(),
                'from' => $first?->created_at?->toIso8601String(),
                'to' => $last?->created_at?->toIso8601String(),
            ],
            'series' => $points->map(fn (WalletEquitySnapshot $s) => [
                't' => $s->created_at?->toIso8601String(),
                'v' => (float) $s->est_total_usdt,
                'marked' => $s->marked_usdt !== null ? (float) $s->marked_usdt : null,
            ])->values(),
        ]);
    }
}
