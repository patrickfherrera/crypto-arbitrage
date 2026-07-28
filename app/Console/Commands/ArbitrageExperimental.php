<?php

namespace App\Console\Commands;

use App\Models\ArbitrageLog;
use App\Models\CoinArbitrage;
use App\Models\Coin;
use App\Services\BinanceSpotAPI\Convert;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;

class ArbitrageExperimental extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:arbitrage-experimental {--coin_arbitrage_id=1} {--interval=60}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run Binance Convert triangular quotes, log results (test_mode skips accept)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $interval = max(1, (int) $this->option('interval'));

        $coinArbitrage = CoinArbitrage::with(['coin_one', 'coin_two', 'coin_three'])
            ->find($this->option('coin_arbitrage_id'));

        if (is_null($coinArbitrage)) {
            $this->error('coin_arbitrage_id not found or invalid.');
            return self::FAILURE;
        }

        $id = $coinArbitrage->id;
        $this->info("Starting Convert arb for #{$id}");

        while (CoinArbitrage::query()->whereKey($id)->where('enabled', true)->exists()) {
            $coinArbitrage->refresh();
            $coinArbitrage->load(['coin_one', 'coin_two', 'coin_three']);
            $this->scan($coinArbitrage);
            sleep($interval);
        }
        return self::SUCCESS;
    }

    protected function scan(CoinArbitrage $coinArbitrage): void
    {
        $leg1 = $this->direction($coinArbitrage->coin_one, $coinArbitrage->coin_one_price);
        $leg2 = $this->direction($coinArbitrage->coin_two, $coinArbitrage->coin_two_price);
        $leg3 = $this->direction($coinArbitrage->coin_three, $coinArbitrage->coin_three_price);

        $requireQuoteId = (int) $coinArbitrage->test_mode === 0;

        // Leg 1: spend capital (USDT) as fromAmount
        $quote1 = $this->requestQuote([
            'fromAsset' => $leg1['from'],
            'toAsset' => $leg1['to'],
            'fromAmount' => round((float) $coinArbitrage->capital, 8),
        ], $requireQuoteId);

        if (! $quote1) {
            return;
        }

        $quote2 = $this->requestQuote([
            'fromAsset' => $leg2['from'],
            'toAsset' => $leg2['to'],
            'fromAmount' => $quote1['toAmount'],
        ], $requireQuoteId);

        if (! $quote2) {
            return;
        }

        $quote3 = $this->requestQuote([
            'fromAsset' => $leg3['from'],
            'toAsset' => $leg3['to'],
            'fromAmount' => $quote2['toAmount'],
        ], $requireQuoteId);

        if (! $quote3) {
            return;
        }

        $initial = (float) $quote1['fromAmount'];
        $final = (float) $quote3['toAmount'];
        $profit = $final - $initial;
        $status = $profit > 0 ? 'PROFITABLE' : 'NOT_PROFITABLE';

        ArbitrageLog::create([
            'capital' => $initial,
            'final_amount' => $final,
            'profit' => $profit,
            'status' => $status,
            'coin_arbitrage_id' => $coinArbitrage->id,
        ]);

        if ($profit > 0) {
            $this->info("PROFITABLE: {$initial} → {$final} (profit={$profit})");
        } else {
            $this->warn("NOT_PROFITABLE: {$initial} → {$final} (profit={$profit})");
        }

        // Phase 1: never accept quotes in test mode
        if ($profit > 0 && (int) $coinArbitrage->test_mode === 0) {
            $convert = new Convert;
            $convert->acceptQuote(['quoteId' => $quote1['quoteId']]);
            $convert->acceptQuote(['quoteId' => $quote2['quoteId']]);
            $convert->acceptQuote(['quoteId' => $quote3['quoteId']]);
            $this->info('Accepted all three Convert quotes.');
        }
    }

    /**
     * askPrice = buy base with quote → from quote, to base
     * bidPrice = sell base for quote → from base, to quote
     *
     * @return array{from: string, to: string}
     */
    protected function direction(Coin $coin, string $priceSide): array
    {
        if ($priceSide === 'askPrice') {
            return [
                'from' => $coin->quote_asset,
                'to' => $coin->base_asset,
            ];
        }
        return [
            'from' => $coin->base_asset,
            'to' => $coin->quote_asset,
        ];
    }

    protected function requestQuote(array $params, bool $requireQuoteId = true): ?array
    {
        $response = (new Convert)->sendQuote($params);
    
        sleep(1);
        if ($response instanceof ClientException) {
            $this->error('Convert quote failed: '.$response->getMessage());
            $this->line(json_encode($params));
            return null;
        }
    
        $decoded = json_decode($response->getBody()->getContents(), true);
        if (! is_array($decoded) || ! Arr::has($decoded, ['fromAmount', 'toAmount'])) {
            $this->error('Convert quote invalid: '.json_encode($decoded));
            return null;
        }
    
        if ($requireQuoteId && ! Arr::has($decoded, 'quoteId')) {
            $this->error('Convert quote missing quoteId: '.json_encode($decoded));
            return null;
        }
    
        return $decoded;
    }
}
