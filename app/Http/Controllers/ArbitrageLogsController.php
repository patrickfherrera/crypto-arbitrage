<?php

namespace App\Http\Controllers;

use App\Models\ArbitrageLog;
use App\Models\CoinArbitrage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Request;
use Inertia\Inertia;
use Inertia\Response;

class ArbitrageLogsController extends Controller
{
    public function index(): Response
    {
        $sort = Request::input('sort', 'newest');
        $triangleSort = Request::input('triangle_sort', 'wins');

        $baseQuery = $this->filteredLogsQuery();

        $summaryRow = (clone $baseQuery)
            ->whereNotNull('profit_pct')
            ->selectRaw('COUNT(*) as total, MAX(profit_pct) as best_pct, AVG(profit_pct) as mean_pct')
            ->first();

        $labels = CoinArbitrage::with(['coin_one', 'coin_two', 'coin_three'])
            ->get()
            ->mapWithKeys(fn (CoinArbitrage $a) => [
                $a->id => $a->coin_one->symbol.' → '.$a->coin_two->symbol.' → '.$a->coin_three->symbol,
            ]);

        $byTriangleQuery = (clone $baseQuery)
            ->whereNotNull('profit_pct')
            ->selectRaw('coin_arbitrage_id, COUNT(*) as total, SUM(CASE WHEN profit_pct > 0 THEN 1 ELSE 0 END) as wins, MAX(profit_pct) as best_pct, AVG(profit_pct) as mean_pct')
            ->groupBy('coin_arbitrage_id');

        match ($triangleSort) {
            'win_rate' => $byTriangleQuery
                ->orderByRaw('(SUM(CASE WHEN profit_pct > 0 THEN 1 ELSE 0 END) / COUNT(*)) DESC')
                ->orderByDesc('best_pct'),
            'best_pct' => $byTriangleQuery->orderByDesc('best_pct')->orderByDesc('wins'),
            'mean_pct' => $byTriangleQuery->orderByDesc('mean_pct')->orderByDesc('wins'),
            'rows' => $byTriangleQuery->orderByDesc('total')->orderByDesc('best_pct'),
            default => $byTriangleQuery->orderByDesc('wins')->orderByDesc('best_pct'),
        };

        return Inertia::render('ArbitrageLogs/Index', [
            'filters' => Request::all(
                'search',
                'profitable',
                'direction',
                'coin_arbitrage_id',
                'sort',
                'triangle_sort'
            ),
            'arbitrages' => CoinArbitrage::with(['coin_one', 'coin_two', 'coin_three'])
                ->orderBy('id')
                ->get()
                ->map(fn (CoinArbitrage $a) => [
                    'id' => $a->id,
                    'label' => $a->coin_one->symbol.' → '.$a->coin_two->symbol.' → '.$a->coin_three->symbol,
                ]),
            'summary' => [
                'total' => (int) ($summaryRow->total ?? 0),
                'best_pct' => $summaryRow->best_pct !== null ? (float) $summaryRow->best_pct : null,
                'mean_pct' => $summaryRow->mean_pct !== null ? (float) $summaryRow->mean_pct : null,
            ],
            'byTriangle' => $byTriangleQuery
                ->paginate(10, ['*'], 'triangle_page')
                ->withQueryString()
                ->through(fn ($row) => [
                    'coin_arbitrage_id' => (int) $row->coin_arbitrage_id,
                    'path' => $labels[$row->coin_arbitrage_id] ?? ('#'.$row->coin_arbitrage_id),
                    'total' => (int) $row->total,
                    'wins' => (int) $row->wins,
                    'win_rate' => $row->total > 0 ? ((int) $row->wins / (int) $row->total) * 100 : 0,
                    'best_pct' => $row->best_pct !== null ? (float) $row->best_pct : null,
                    'mean_pct' => $row->mean_pct !== null ? (float) $row->mean_pct : null,
                ]),
            'arbitrageLogs' => $baseQuery
                ->with([
                    'coin_arbitrage',
                    'coin_arbitrage.coin_one',
                    'coin_arbitrage.coin_two',
                    'coin_arbitrage.coin_three',
                ])
                ->whereNotNull('profit_pct')
                ->when($sort === 'best_pct', function ($query) {
                    $query->orderByDesc('profit_pct')->orderByDesc('created_at');
                })
                ->when($sort === 'worst_pct', function ($query) {
                    $query->orderBy('profit_pct')->orderByDesc('created_at');
                })
                ->when($sort === 'oldest', function ($query) {
                    $query->orderBy('created_at');
                })
                ->when(! in_array($sort, ['best_pct', 'worst_pct', 'oldest'], true), function ($query) {
                    $query->orderByDesc('created_at');
                })
                ->paginate(10)
                ->withQueryString()
                ->through(fn ($arbitrageLog) => [
                    'id' => $arbitrageLog->id,
                    'created_at' => $arbitrageLog->created_at->toIso8601String(),
                    'path' => $arbitrageLog->coin_arbitrage->coin_one->symbol
                        .' → '.$arbitrageLog->coin_arbitrage->coin_two->symbol
                        .' → '.$arbitrageLog->coin_arbitrage->coin_three->symbol,
                    'capital' => number_format($arbitrageLog->capital, 2),
                    'profit' => number_format($arbitrageLog->profit, 6),
                    'status' => str_replace('_', ' ', $arbitrageLog->status),
                    'profit_pct' => $arbitrageLog->profit_pct !== null
                        ? (float) $arbitrageLog->profit_pct
                        : null,
                    'direction' => $arbitrageLog->direction,
                    'quote_age_ms' => $arbitrageLog->quote_age_ms,
                ]),
        ]);
    }

    protected function filteredLogsQuery(): Builder
    {
        return ArbitrageLog::query()
            ->when(Request::filled('profitable'), function ($query) {
                $query->where('status', Request::input('profitable'));
            })
            ->when(Request::filled('direction'), function ($query) {
                $query->where('direction', Request::input('direction'));
            })
            ->when(Request::filled('coin_arbitrage_id'), function ($query) {
                $query->where('coin_arbitrage_id', Request::input('coin_arbitrage_id'));
            })
            ->when(Request::filled('search'), function ($query) {
                $search = Request::input('search');
                $query->whereHas('coin_arbitrage', function ($q) use ($search) {
                    $q->whereHas('coin_one', fn ($c) => $c->where('symbol', 'like', "%{$search}%"))
                        ->orWhereHas('coin_two', fn ($c) => $c->where('symbol', 'like', "%{$search}%"))
                        ->orWhereHas('coin_three', fn ($c) => $c->where('symbol', 'like', "%{$search}%"));
                });
            });
    }
}
