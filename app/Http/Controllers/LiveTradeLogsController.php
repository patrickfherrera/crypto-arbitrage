<?php

namespace App\Http\Controllers;

use App\Models\LiveTradeLog;
use Illuminate\Support\Facades\Request;
use Inertia\Inertia;
use Inertia\Response;

class LiveTradeLogsController extends Controller
{
    public function index(): Response
    {
        $summary = LiveTradeLog::query()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed")
            ->selectRaw("SUM(CASE WHEN status = 'partial' THEN 1 ELSE 0 END) as partial")
            ->selectRaw('COALESCE(SUM(usdt_delta), 0) as net_usdt')
            ->selectRaw('COALESCE(AVG(usdt_delta_pct), 0) as mean_delta_pct')
            ->selectRaw('COALESCE(SUM(equity_delta), 0) as net_equity')
            ->selectRaw('COALESCE(AVG(equity_delta_pct), 0) as mean_equity_delta_pct')
            ->first();

        return Inertia::render('LiveTradeLogs/Index', [
            'summary' => [
                'total' => (int) ($summary->total ?? 0),
                'completed' => (int) ($summary->completed ?? 0),
                'partial' => (int) ($summary->partial ?? 0),
                'net_usdt' => (float) ($summary->net_usdt ?? 0),
                'mean_delta_pct' => (float) ($summary->mean_delta_pct ?? 0),
                'net_equity' => (float) ($summary->net_equity ?? 0),
                'mean_equity_delta_pct' => (float) ($summary->mean_equity_delta_pct ?? 0),
            ],
            'logs' => LiveTradeLog::query()
                ->with([
                    'coinArbitrage:id,coin_one_id,coin_two_id,coin_three_id',
                    'coinArbitrage.coin_one:id,symbol',
                    'coinArbitrage.coin_two:id,symbol',
                    'coinArbitrage.coin_three:id,symbol',
                ])
                ->when(Request::filled('status'), fn ($q) => $q->where('status', Request::input('status')))
                ->orderByDesc('id')
                ->paginate(25)
                ->withQueryString()
                ->through(function (LiveTradeLog $log) {
                    $arb = $log->coinArbitrage;
                    $path = $arb
                        ? $arb->coin_one->symbol.' → '.$arb->coin_two->symbol.' → '.$arb->coin_three->symbol
                        : '—';

                    return [
                        'id' => $log->id,
                        'created_at' => $log->created_at?->toIso8601String(),
                        'source' => $log->source,
                        'path' => $path,
                        'direction' => $log->direction,
                        'capital' => $log->capital,
                        'usdt_before' => $log->usdt_before,
                        'usdt_after' => $log->usdt_after,
                        'usdt_delta' => $log->usdt_delta,
                        'usdt_delta_pct' => $log->usdt_delta_pct,
                        'equity_before' => $log->equity_before,
                        'equity_after' => $log->equity_after,
                        'equity_delta' => $log->equity_delta,
                        'equity_delta_pct' => $log->equity_delta_pct,
                        'sim_profit_pct' => $log->sim_profit_pct,
                        'quote_age_ms' => $log->quote_age_ms,
                        'status' => $log->status,
                        'error' => $log->error,
                    ];
                }),
            'filters' => [
                'status' => Request::input('status'),
            ],
        ]);
    }
}
