<?php

namespace App\Jobs;

use App\Mail\ArbitrageLogsExportMail;
use App\Models\User;
use App\Services\ArbitrageLogCsvExporter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ExportArbitrageLogsCsv implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public int $tries = 1;

    /**
     * @param  array{range?: string, sort?: string, profitable?: string|null, direction?: string|null, coin_arbitrage_id?: string|int|null, search?: string|null}  $filters
     */
    public function __construct(
        public int $userId,
        public array $filters,
    ) {}

    public function handle(ArbitrageLogCsvExporter $exporter): void
    {
        $user = User::query()->find($this->userId);
        if (! $user?->email) {
            return;
        }

        $range = $this->filters['range'] ?? '1h';
        $filename = 'arbitrage-logs-'.$range.'-'.now()->format('Y-m-d-His').'.csv';
        $relativePath = 'exports/'.$filename;

        try {
            $rowCount = $exporter->writeToDisk($this->filters, $relativePath);
            $absolutePath = Storage::disk('local')->path($relativePath);

            Mail::to($user->email)->send(new ArbitrageLogsExportMail(
                absolutePath: $absolutePath,
                filename: $filename,
                rowCount: $rowCount,
                rangeLabel: $this->rangeLabel($range),
            ));
        } finally {
            Storage::disk('local')->delete($relativePath);
        }
    }

    public function failed(?Throwable $exception): void
    {
        report($exception);
    }

    protected function rangeLabel(string $range): string
    {
        return match ($range) {
            '24h' => 'last 24h',
            '7d' => 'last 7d',
            '30d' => 'last 30d',
            'all' => 'all time',
            default => 'last 1h',
        };
    }
}
