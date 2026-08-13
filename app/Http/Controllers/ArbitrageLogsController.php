<?php

namespace App\Http\Controllers;

use App\Jobs\ExportArbitrageLogsCsv;
use App\Models\CoinArbitrage;
use App\Services\ArbitrageLogCsvExporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Request;
use Inertia\Inertia;
use Inertia\Response;

class ArbitrageLogsController extends Controller
{
    public function __construct(
        protected ArbitrageLogCsvExporter $exporter,
    ) {}

    public function index(): Response
    {
        $sort = Request::input('sort', 'newest');
        $triangleSort = Request::input('triangle_sort', 'wins');
        $range = Request::input('range', '1h');

        $filterKey = [
            'range' => $range,
            'profitable' => Request::input('profitable'),
            'direction' => Request::input('direction'),
            'coin_arbitrage_id' => Request::input('coin_arbitrage_id'),
            'search' => Request::input('search'),
            'triangle_sort' => $triangleSort,
        ];

        $arbitrages = CoinArbitrage::with(['coin_one', 'coin_two', 'coin_three'])
            ->orderBy('id')
            ->get();

        $labels = $arbitrages->mapWithKeys(fn (CoinArbitrage $a) => [
            $a->id => $a->coin_one->symbol.' → '.$a->coin_two->symbol.' → '.$a->coin_three->symbol,
        ]);

        $stats = Cache::remember(
            'arb_logs_stats:'.md5(json_encode($filterKey)),
            30,
            function () use ($range, $triangleSort, $labels) {
                $baseQuery = $this->exporter->filteredLogsQuery([
                    'range' => $range,
                    'profitable' => Request::input('profitable'),
                    'direction' => Request::input('direction'),
                    'coin_arbitrage_id' => Request::input('coin_arbitrage_id'),
                    'search' => Request::input('search'),
                ])->whereNotNull('profit_pct');

                $summaryRow = (clone $baseQuery)
                    ->selectRaw('COUNT(*) as total, MAX(profit_pct) as best_pct, AVG(profit_pct) as mean_pct')
                    ->first();

                $byTriangleQuery = (clone $baseQuery)
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

                $byTriangle = $byTriangleQuery->get()->map(fn ($row) => [
                    'coin_arbitrage_id' => (int) $row->coin_arbitrage_id,
                    'path' => $labels[$row->coin_arbitrage_id] ?? ('#'.$row->coin_arbitrage_id),
                    'total' => (int) $row->total,
                    'wins' => (int) $row->wins,
                    'win_rate' => $row->total > 0 ? ((int) $row->wins / (int) $row->total) * 100 : 0,
                    'best_pct' => $row->best_pct !== null ? (float) $row->best_pct : null,
                    'mean_pct' => $row->mean_pct !== null ? (float) $row->mean_pct : null,
                ])->values();

                return [
                    'summary' => [
                        'total' => (int) ($summaryRow->total ?? 0),
                        'best_pct' => $summaryRow->best_pct !== null ? (float) $summaryRow->best_pct : null,
                        'mean_pct' => $summaryRow->mean_pct !== null ? (float) $summaryRow->mean_pct : null,
                        'range' => $range,
                    ],
                    'byTriangle' => $byTriangle,
                ];
            }
        );

        $trianglePage = max(1, (int) Request::input('triangle_page', 1));
        $trianglePerPage = 10;
        $triangleItems = collect($stats['byTriangle']);
        $byTriangle = new LengthAwarePaginator(
            $triangleItems->forPage($trianglePage, $trianglePerPage)->values(),
            $triangleItems->count(),
            $trianglePerPage,
            $trianglePage,
            [
                'path' => Request::url(),
                'pageName' => 'triangle_page',
                'query' => Request::query(),
            ]
        );

        $baseQuery = $this->exporter->filteredLogsQuery([
            'range' => $range,
            'profitable' => Request::input('profitable'),
            'direction' => Request::input('direction'),
            'coin_arbitrage_id' => Request::input('coin_arbitrage_id'),
            'search' => Request::input('search'),
        ])
            ->with([
                'coin_arbitrage:id,coin_one_id,coin_two_id,coin_three_id',
                'coin_arbitrage.coin_one:id,symbol',
                'coin_arbitrage.coin_two:id,symbol',
                'coin_arbitrage.coin_three:id,symbol',
            ])
            ->whereNotNull('profit_pct');

        $this->exporter->applyLogSort($baseQuery, $sort);

        return Inertia::render('ArbitrageLogs/Index', [
            'filters' => [
                'search' => Request::input('search'),
                'profitable' => Request::input('profitable'),
                'direction' => Request::input('direction'),
                'coin_arbitrage_id' => Request::input('coin_arbitrage_id'),
                'sort' => $sort,
                'triangle_sort' => $triangleSort,
                'range' => $range,
            ],
            'arbitrages' => $arbitrages->map(fn (CoinArbitrage $a) => [
                'id' => $a->id,
                'label' => $labels[$a->id],
            ]),
            'summary' => $stats['summary'],
            'byTriangle' => $byTriangle,
            'arbitrageLogs' => $baseQuery
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

    public function export(): RedirectResponse
    {
        $filters = [
            'range' => Request::input('range', '1h'),
            'sort' => Request::input('sort', 'newest'),
            'profitable' => Request::input('profitable'),
            'direction' => Request::input('direction'),
            'coin_arbitrage_id' => Request::input('coin_arbitrage_id'),
            'search' => Request::input('search'),
        ];

        ExportArbitrageLogsCsv::dispatch(Auth::id(), $filters);

        return redirect()
            ->route('arbitrage-logs.index', array_filter($filters, fn ($v) => $v !== null && $v !== ''))
            ->with('success', 'Export started — check your email shortly.');
    }
}
