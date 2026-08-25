<?php

namespace App\Console\Commands;

use App\Models\CoinArbitrage;
use App\Services\Binance\FeeResolver;

class ArbitrageLiveTrade extends TriangularArbitrage
{
    protected $signature = 'arbitrage:live-trade
                            {--id= : Coin arbitrage id (skip interactive choice)}
                            {--force : Execute even if below min profit / capital / age gates}';

    protected $description = 'Pick an enabled live (non-test) triangle and run one trade';

    public function handle(FeeResolver $fees): int
    {
        $candidates = CoinArbitrage::query()
            ->where('enabled', true)
            ->where('test_mode', false)
            ->with(['coin_one', 'coin_two', 'coin_three'])
            ->orderBy('id')
            ->get();

        if ($candidates->isEmpty()) {
            $this->error('No enabled live triangles (enabled=1, test_mode=0).');

            return self::FAILURE;
        }

        $arb = $this->resolveArbitrage($candidates);
        if (! $arb) {
            return self::FAILURE;
        }

        $path = $arb->coin_one->symbol.' → '.$arb->coin_two->symbol.' → '.$arb->coin_three->symbol;
        $fee = $fees->takerFee();
        $this->info("Selected #{$arb->id} {$path} capital={$arb->capital} fee={$fee}");

        $prices = $this->fetchPrices([
            $arb->coin_one->symbol,
            $arb->coin_two->symbol,
            $arb->coin_three->symbol,
        ]);

        if (! $prices) {
            $this->error('Missing/stale Redis bookTicker — is binance:book-ticker-feed running?');

            return self::FAILURE;
        }

        $mode = (string) config('binance.direction_mode', 'prefer_reverse');
        $configuredCapital = (float) $arb->capital;

        $forwardLegs = [
            ['symbol' => $arb->coin_one->symbol, 'side' => $arb->coin_one_price],
            ['symbol' => $arb->coin_two->symbol, 'side' => $arb->coin_two_price],
            ['symbol' => $arb->coin_three->symbol, 'side' => $arb->coin_three_price],
        ];
        $reverseLegs = [
            ['symbol' => $arb->coin_three->symbol, 'side' => $this->flipSide($arb->coin_three_price)],
            ['symbol' => $arb->coin_two->symbol, 'side' => $this->flipSide($arb->coin_two_price)],
            ['symbol' => $arb->coin_one->symbol, 'side' => $this->flipSide($arb->coin_one_price)],
        ];

        [$forward, $reverse] = $this->scoreDirections(
            $arb,
            $prices,
            $configuredCapital,
            $fee,
            $forwardLegs,
            $reverseLegs,
            $mode
        );
        $best = $this->pickBestDirection($forward, $reverse, $mode);

        if ($best['profit_pct'] > 0 && config('binance.confirm_green', true)) {
            usleep(50_000);
            $confirmed = $this->fetchPrices([
                $arb->coin_one->symbol,
                $arb->coin_two->symbol,
                $arb->coin_three->symbol,
            ]);
            if ($confirmed) {
                [$forward, $reverse] = $this->scoreDirections(
                    $arb,
                    $confirmed,
                    $configuredCapital,
                    $fee,
                    $forwardLegs,
                    $reverseLegs,
                    $mode
                );
                $best = $this->pickBestDirection($forward, $reverse, $mode);
                $prices = $confirmed;
            }
        }

        $quoteAgeMs = $this->maxQuoteAgeMs($prices);
        $maxAge = (int) config('binance.max_quote_age_ms', 300);
        $startUSDT = (float) $best['capital'];
        $minExecute = (float) config('binance.min_execute_profit_pct', 0.05);
        $minCapital = (float) config('binance.min_execute_capital', 3);
        $staleGreen = $best['profit_pct'] > 0 && $maxAge > 0 && $quoteAgeMs > $maxAge;
        $isGreen = $best['profit'] > 0 && ! $staleGreen;
        $passesGates = $isGreen
            && $best['profit_pct'] >= $minExecute
            && $startUSDT >= $minCapital;

        $this->table(
            ['field', 'value'],
            [
                ['direction', $best['direction']],
                ['profit_pct', number_format($best['profit_pct'], 6).'%'],
                ['profit', number_format($best['profit'], 8)],
                ['capital', number_format($startUSDT, 4)],
                ['quote_age_ms', $quoteAgeMs],
                ['stale', $staleGreen ? 'yes' : 'no'],
                ['passes_gates', $passesGates ? 'yes' : 'no'],
            ]
        );

        if (! $passesGates && ! $this->option('force')) {
            $this->error('Does not pass execute gates. Re-run with --force to trade anyway.');

            return self::FAILURE;
        }

        if (! $passesGates && $this->option('force')) {
            $this->warn('--force: executing despite failed gates.');
            if ($startUSDT <= 0) {
                $startUSDT = $configuredCapital;
            }
        }

        if (! $this->confirm("Execute LIVE {$best['direction']} on #{$arb->id} with ~{$startUSDT} USDT?", false)) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        $liveLog = $this->executeLiveWithUsdtLog(
            $arb,
            $startUSDT,
            $best['direction'],
            'live_trade',
            [
                'sim_profit_pct' => $best['profit_pct'],
                'quote_age_ms' => $quoteAgeMs,
            ]
        );

        $this->table(
            ['field', 'value'],
            [
                ['live_trade_log_id', $liveLog->id],
                ['status', $liveLog->status],
                ['usdt_before', number_format((float) $liveLog->usdt_before, 8)],
                ['usdt_after', number_format((float) ($liveLog->usdt_after ?? 0), 8)],
                ['usdt_delta', number_format((float) ($liveLog->usdt_delta ?? 0), 8)],
                ['usdt_delta_pct (vs capital)', number_format((float) ($liveLog->usdt_delta_pct ?? 0), 6).'%'],
                ['sim_profit_pct', number_format((float) ($liveLog->sim_profit_pct ?? 0), 6).'%'],
                ['error', $liveLog->error ?? '—'],
            ]
        );

        if ($liveLog->balances_after) {
            $this->line('Balances after: '.json_encode($liveLog->balances_after));
        }

        $this->info('Saved to live_trade_logs (realized USDT PnL).');

        return $liveLog->status === 'completed' ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, CoinArbitrage>  $candidates
     */
    protected function resolveArbitrage($candidates): ?CoinArbitrage
    {
        $id = $this->option('id');
        if ($id !== null && $id !== '') {
            $arb = $candidates->firstWhere('id', (int) $id);
            if (! $arb) {
                $this->error("Id {$id} is not in the enabled live list.");

                return null;
            }

            return $arb;
        }

        $labels = $candidates->mapWithKeys(function (CoinArbitrage $a) {
            $path = $a->coin_one->symbol.' → '.$a->coin_two->symbol.' → '.$a->coin_three->symbol;

            return [$a->id => "#{$a->id}  {$path}  capital={$a->capital}"];
        })->all();

        $picked = $this->choice('Choose a live triangle', array_values($labels));
        $id = (int) str_replace('#', '', explode(' ', $picked)[0]);

        return $candidates->firstWhere('id', $id);
    }
}
