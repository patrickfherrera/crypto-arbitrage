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
        $requireQuoteId = (int) $coinArbitrage->test_mode === 0;
        $capital = round((float) $coinArbitrage->capital, 8);
        $minProfit = (float) ($coinArbitrage->profit ?? 0);
    
        $forward = $this->quotePath([
            ['coin' => $coinArbitrage->coin_one, 'side' => $coinArbitrage->coin_one_price],
            ['coin' => $coinArbitrage->coin_two, 'side' => $coinArbitrage->coin_two_price],
            ['coin' => $coinArbitrage->coin_three, 'side' => $coinArbitrage->coin_three_price],
        ], $capital, $requireQuoteId, 'forward');
    
        // Reverse cycle: flip order + flip ask/bid on each leg
        $reverse = $this->quotePath([
            ['coin' => $coinArbitrage->coin_three, 'side' => $this->flipSide($coinArbitrage->coin_three_price)],
            ['coin' => $coinArbitrage->coin_two, 'side' => $this->flipSide($coinArbitrage->coin_two_price)],
            ['coin' => $coinArbitrage->coin_one, 'side' => $this->flipSide($coinArbitrage->coin_one_price)],
        ], $capital, $requireQuoteId, 'reverse');
    
        $candidates = array_values(array_filter([$forward, $reverse]));
        if ($candidates === []) {
            return;
        }
    
        usort($candidates, fn ($a, $b) => $b['profit'] <=> $a['profit']);
        $best = $candidates[0];
    
        ArbitrageLog::create([
            'capital' => $best['initial'],
            'final_amount' => $best['final'],
            'profit' => $best['profit'],
            'status' => $best['profit'] > 0 ? 'PROFITABLE' : 'NOT_PROFITABLE',
            'coin_arbitrage_id' => $coinArbitrage->id,
        ]);
    
        // Accept only if profitable AND >= CoinArbitrage.profit threshold
        if (
            $best['profit'] > 0
            && $best['profit'] >= $minProfit
            && (int) $coinArbitrage->test_mode === 0
            && ! empty($best['quoteIds'])
        ) {
            $convert = new Convert;
            foreach ($best['quoteIds'] as $quoteId) {
                $convert->acceptQuote(['quoteId' => $quoteId]);
            }
        }
    }
    
    protected function flipSide(string $priceSide): string
    {
        return $priceSide === 'askPrice' ? 'bidPrice' : 'askPrice';
    }
    
    /** Walk 3 Convert quotes; return initial/final/profit/quoteIds or null */
    protected function quotePath(array $legs, float $capital, bool $requireQuoteId, string $direction): ?array
    {
        $amount = $capital;
        $initial = null;
        $quoteIds = [];
    
        foreach ($legs as $index => $leg) {
            $flow = $this->direction($leg['coin'], $leg['side']);
            $quote = $this->requestQuote([
                'fromAsset' => $flow['from'],
                'toAsset' => $flow['to'],
                'fromAmount' => round($amount, 8),
            ], $requireQuoteId);
    
            if (! $quote) {
                return null;
            }
    
            if ($index === 0) {
                $initial = (float) $quote['fromAmount'];
            }
    
            $amount = (float) $quote['toAmount'];
            if (! empty($quote['quoteId'])) {
                $quoteIds[] = $quote['quoteId'];
            }
        }
    
        $final = $amount;
        $profit = $final - $initial;
    
        return compact('direction', 'initial', 'final', 'profit') + ['quoteIds' => $quoteIds];
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
