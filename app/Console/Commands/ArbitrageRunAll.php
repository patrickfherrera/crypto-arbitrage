<?php

namespace App\Console\Commands;

use App\Models\CoinArbitrage;
use App\Services\Binance\FeeResolver;

class ArbitrageRunAll extends TriangularArbitrage
{
    protected $signature = 'arbitrage:run-all {--interval=1}';

    protected $description = 'Run all enabled triangular arbitrages in one process (Forge daemon)';

    public function handle(FeeResolver $fees): int
    {
        $interval = max(0, (int) $this->option('interval'));
        $fee = $fees->takerFee();
        $this->info("arbitrage:run-all starting; taker fee={$fee}");

        /** @var array<int, string|null> $lastFp */
        $lastFp = [];

        while (true) {
            $rows = CoinArbitrage::query()
                ->where('enabled', true)
                ->with(['coin_one', 'coin_two', 'coin_three'])
                ->get();

            if ($rows->isEmpty()) {
                $this->warn('No enabled triangles; sleeping...');
                usleep(max(50_000, $interval * 1_000_000));
                continue;
            }

            foreach ($rows as $coinArbitrage) {
                $id = $coinArbitrage->id;

                $prices = $this->fetchPrices([
                    $coinArbitrage->coin_one->symbol,
                    $coinArbitrage->coin_two->symbol,
                    $coinArbitrage->coin_three->symbol,
                ]);

                if (! $prices) {
                    continue;
                }

                $fp = md5(json_encode([
                    $prices[$coinArbitrage->coin_one->symbol]['bidPrice'] ?? null,
                    $prices[$coinArbitrage->coin_one->symbol]['askPrice'] ?? null,
                    $prices[$coinArbitrage->coin_two->symbol]['bidPrice'] ?? null,
                    $prices[$coinArbitrage->coin_two->symbol]['askPrice'] ?? null,
                    $prices[$coinArbitrage->coin_three->symbol]['bidPrice'] ?? null,
                    $prices[$coinArbitrage->coin_three->symbol]['askPrice'] ?? null,
                ]));

                if (($lastFp[$id] ?? null) === $fp) {
                    continue;
                }

                $lastFp[$id] = $fp;
                $this->simulate($coinArbitrage, $prices, $fee);
            }

            // Drop fingerprints for disabled ids so re-enable rescans cleanly
            $enabledIds = $rows->pluck('id')->all();
            foreach (array_keys($lastFp) as $id) {
                if (! in_array($id, $enabledIds, true)) {
                    unset($lastFp[$id]);
                }
            }

            usleep($interval > 0 ? $interval * 1_000_000 : 100_000);
        }
    }
}