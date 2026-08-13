<?php

namespace App\Services;

use App\Models\ArbitrageLog;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

class ArbitrageLogCsvExporter
{
    public const MAX_ROWS = 100_000;

    /**
     * @param  array{range?: string, sort?: string, profitable?: string|null, direction?: string|null, coin_arbitrage_id?: string|int|null, search?: string|null}  $filters
     */
    public function writeToDisk(array $filters, string $relativePath): int
    {
        $disk = Storage::disk('local');
        $disk->makeDirectory(dirname($relativePath));

        $fullPath = $disk->path($relativePath);
        $out = fopen($fullPath, 'w');

        fputcsv($out, [
            'id',
            'created_at',
            'path',
            'capital',
            'profit',
            'profit_pct',
            'direction',
            'quote_age_ms',
            'status',
            'coin_arbitrage_id',
        ]);

        $query = $this->filteredLogsQuery($filters)
            ->with([
                'coin_arbitrage:id,coin_one_id,coin_two_id,coin_three_id',
                'coin_arbitrage.coin_one:id,symbol',
                'coin_arbitrage.coin_two:id,symbol',
                'coin_arbitrage.coin_three:id,symbol',
            ])
            ->whereNotNull('profit_pct');

        $this->applyLogSort($query, $filters['sort'] ?? 'newest');

        $count = 0;
        foreach ($query->cursor() as $log) {
            if ($count >= self::MAX_ROWS) {
                break;
            }

            $path = $log->coin_arbitrage
                ? $log->coin_arbitrage->coin_one->symbol
                    .' → '.$log->coin_arbitrage->coin_two->symbol
                    .' → '.$log->coin_arbitrage->coin_three->symbol
                : '';

            fputcsv($out, [
                $log->id,
                $log->created_at?->toIso8601String(),
                $path,
                $log->capital,
                $log->profit,
                $log->profit_pct,
                $log->direction,
                $log->quote_age_ms,
                $log->status,
                $log->coin_arbitrage_id,
            ]);
            $count++;
        }

        fclose($out);

        return $count;
    }

    /**
     * @param  array{range?: string, profitable?: string|null, direction?: string|null, coin_arbitrage_id?: string|int|null, search?: string|null}  $filters
     */
    public function filteredLogsQuery(array $filters): Builder
    {
        $range = $filters['range'] ?? '1h';

        return ArbitrageLog::query()
            ->when($this->rangeStart($range), function ($query, Carbon $start) {
                $query->where('created_at', '>=', $start);
            })
            ->when(! empty($filters['profitable']), function ($query) use ($filters) {
                $query->where('status', $filters['profitable']);
            })
            ->when(! empty($filters['direction']), function ($query) use ($filters) {
                $query->where('direction', $filters['direction']);
            })
            ->when(! empty($filters['coin_arbitrage_id']), function ($query) use ($filters) {
                $query->where('coin_arbitrage_id', $filters['coin_arbitrage_id']);
            })
            ->when(! empty($filters['search']), function ($query) use ($filters) {
                $search = $filters['search'];
                $query->whereHas('coin_arbitrage', function ($q) use ($search) {
                    $q->whereHas('coin_one', fn ($c) => $c->where('symbol', 'like', "%{$search}%"))
                        ->orWhereHas('coin_two', fn ($c) => $c->where('symbol', 'like', "%{$search}%"))
                        ->orWhereHas('coin_three', fn ($c) => $c->where('symbol', 'like', "%{$search}%"));
                });
            });
    }

    public function applyLogSort(Builder $query, string $sort): void
    {
        if ($sort === 'best_pct') {
            $query->orderByDesc('profit_pct')->orderByDesc('created_at');
        } elseif ($sort === 'worst_pct') {
            $query->orderBy('profit_pct')->orderByDesc('created_at');
        } elseif ($sort === 'oldest') {
            $query->orderBy('created_at');
        } else {
            $query->orderByDesc('created_at');
        }
    }

    public function rangeStart(string $range): ?Carbon
    {
        return match ($range) {
            '24h' => now()->subDay(),
            '7d' => now()->subDays(7),
            '30d' => now()->subDays(30),
            'all' => null,
            default => now()->subHour(),
        };
    }
}
