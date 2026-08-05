<?php

namespace App\Console\Commands;

use App\Models\ArbitrageLog;
use App\Models\Coin;
use App\Models\CoinArbitrage;
use App\Services\BinanceSpotAPI\Market;
use App\Services\BinanceSpotAPI\Trade;
use Illuminate\Console\Command;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use App\Services\Binance\BookTickerStore;
use App\Services\Binance\FeeResolver;

class TriangularArbitrage extends Command
{
    protected $signature = 'arbitrage:run {--interval=1} {--coin_arbitrage_id=}';

    protected $description = 'Run triangular arbitrage simulation 24/7 and log results to DB';

    protected string $apiUrl = 'https://api.binance.com/api/v3';

    /** @var array<string, array<string, mixed>> */
    protected array $exchangeInfoCache = [];

    protected ?PendingRequest $binanceHttp = null;

    protected function binance(): PendingRequest
    {
        return $this->binanceHttp ??= Http::baseUrl($this->apiUrl)
            ->timeout(20)
            ->connectTimeout(10)
            ->acceptJson();
    }

    /**
     * Cached symbol row from /exchangeInfo (filters, etc.).
     *
     * @return array<string, mixed>
     */
    protected function cachedExchangeSymbol(string $symbol): array
    {
        if (! isset($this->exchangeInfoCache[$symbol])) {
            $response = $this->binance()->get('exchangeInfo', ['symbol' => $symbol]);
            if (! $response->successful()) {
                return [];
            }
            $json = $response->json();
            $this->exchangeInfoCache[$symbol] = $json['symbols'][0] ?? [];
        }

        return $this->exchangeInfoCache[$symbol];
    }

    public function handle(FeeResolver $fees): int
    {
        $interval = max(0, (int) $this->option('interval'));
        $this->info('Starting triangular arbitrage bot');
    
        $coin_arbitrage = CoinArbitrage::with(['coin_one', 'coin_two', 'coin_three'])
            ->find($this->option('coin_arbitrage_id'));
    
        if (! $coin_arbitrage) {
            $this->error('coin_arbitrage_id not found or invalid.');
    
            return self::FAILURE;
        }
    
        $id = $coin_arbitrage->id;
        $lastFp = null;
        $fee = $fees->takerFee();
        $this->info("Using taker fee={$fee}");
    
        while (CoinArbitrage::query()->whereKey($id)->where('enabled', true)->exists()) {
            $coin_arbitrage->refresh();
            $coin_arbitrage->load(['coin_one', 'coin_two', 'coin_three']);
    
            $prices = $this->fetchPrices([
                $coin_arbitrage->coin_one->symbol,
                $coin_arbitrage->coin_two->symbol,
                $coin_arbitrage->coin_three->symbol,
            ]);
    
            if (! $prices) {
                usleep(max(50_000, $interval * 1_000_000));
                continue;
            }
    
            $fp = md5(json_encode([
                $prices[$coin_arbitrage->coin_one->symbol]['bidPrice'] ?? null,
                $prices[$coin_arbitrage->coin_one->symbol]['askPrice'] ?? null,
                $prices[$coin_arbitrage->coin_two->symbol]['bidPrice'] ?? null,
                $prices[$coin_arbitrage->coin_two->symbol]['askPrice'] ?? null,
                $prices[$coin_arbitrage->coin_three->symbol]['bidPrice'] ?? null,
                $prices[$coin_arbitrage->coin_three->symbol]['askPrice'] ?? null,
            ]));
    
            if ($fp !== $lastFp) {
                $lastFp = $fp;
                $this->simulate($coin_arbitrage, $prices, $fee);
            }
    
            usleep($interval > 0 ? $interval * 1_000_000 : 100_000);
        }
    
        return self::SUCCESS;
    }

    protected function simulate(CoinArbitrage $coinArbitrage, array $prices, float $fee): void
    {
        $startUSDT = (float) $coinArbitrage->capital;
        $startUSDT = $this->clampCapitalToDepth($coinArbitrage, $prices, $startUSDT);
    
        if ($startUSDT <= 0) {
            $this->warn('Depth too thin; skip');
            return;
        }
    
        $forwardLegs = [
            ['symbol' => $coinArbitrage->coin_one->symbol, 'side' => $coinArbitrage->coin_one_price],
            ['symbol' => $coinArbitrage->coin_two->symbol, 'side' => $coinArbitrage->coin_two_price],
            ['symbol' => $coinArbitrage->coin_three->symbol, 'side' => $coinArbitrage->coin_three_price],
        ];
    
        $reverseLegs = [
            ['symbol' => $coinArbitrage->coin_three->symbol, 'side' => $this->flipSide($coinArbitrage->coin_three_price)],
            ['symbol' => $coinArbitrage->coin_two->symbol, 'side' => $this->flipSide($coinArbitrage->coin_two_price)],
            ['symbol' => $coinArbitrage->coin_one->symbol, 'side' => $this->flipSide($coinArbitrage->coin_one_price)],
        ];
    
        $forward = $this->simulatePath($forwardLegs, $prices, $startUSDT, $fee, 'forward');
        $reverse = $this->simulatePath($reverseLegs, $prices, $startUSDT, $fee, 'reverse');
    
        $best = collect([$forward, $reverse])->sortByDesc('profit')->first();

        // Confirm greens: stale top-of-book often looks profitable once.
        if ($best['profit_pct'] > 0 && config('binance.confirm_green', true)) {

            $symbols = [
                $coinArbitrage->coin_one->symbol,
                $coinArbitrage->coin_two->symbol,
                $coinArbitrage->coin_three->symbol,
            ];

            usleep(50_000); // 50ms — let the feed tick

            $confirmedPrices = $this->fetchPrices($symbols);

            if (! $confirmedPrices) {

                $this->warn('confirm-green: missing/stale books; skip green');
                
                return;
            }
        
            $startUSDT = $this->clampCapitalToDepth($coinArbitrage, $confirmedPrices, (float) $coinArbitrage->capital);

            if ($startUSDT <= 0) {

                $this->warn('confirm-green: depth too thin; skip');

                return;
            }
        
            $forward = $this->simulatePath($forwardLegs, $confirmedPrices, $startUSDT, $fee, 'forward');
            $reverse = $this->simulatePath($reverseLegs, $confirmedPrices, $startUSDT, $fee, 'reverse');

            $best = collect([$forward, $reverse])->sortByDesc('profit')->first();

            $prices = $confirmedPrices;
        
            if ($best['profit_pct'] <= 0) {

                $this->warn("confirm-green: faded to {$best['profit_pct']}%");

                // Fall through and log the faded result as NOT_PROFITABLE
            } else {

                $this->info("confirm-green: still {$best['profit_pct']}%");

            }
        }
        
        $quoteAgeMs = $this->maxQuoteAgeMs($prices);
        $pct = number_format($best['profit_pct'], 4);
        $minLogPct = (float) config('binance.log_min_profit_pct', -0.05);
        
        if ($best['profit_pct'] <= $minLogPct) {
            $this->warn("skip {$best['direction']} {$pct}% (below {$minLogPct}%) age={$quoteAgeMs}ms");
            return;
        }
        
        ArbitrageLog::create([
            'capital' => $startUSDT,
            'final_amount' => $best['final'],
            'profit' => $best['profit'],
            'profit_pct' => $best['profit_pct'],
            'status' => $best['profit'] > 0 ? 'PROFITABLE' : 'NOT_PROFITABLE',
            'direction' => $best['direction'],
            'quote_age_ms' => $quoteAgeMs,
            'coin_arbitrage_id' => $coinArbitrage->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        $minExecute = (float) config('binance.min_execute_profit_pct', 0.05);
        
        if (
            $best['profit_pct'] >= $minExecute
            && (int) $coinArbitrage->test_mode === 0
        ) {
            $this->info("✅ EXECUTE {$best['direction']} {$pct}% (min {$minExecute}%) age={$quoteAgeMs}ms");
            if ($best['direction'] === 'forward') {
                $this->setParams($coinArbitrage, $startUSDT);
            } else {
                $this->warn('Best was reverse; skipping live orders until setParams supports reverse.');
            }
        } else {
            $this->warn("❌ {$best['direction']} {$pct}% profit={$best['profit']} age={$quoteAgeMs}ms");
        }
    }

    protected function flipSide(string $priceSide): string
    {
        return $priceSide === 'askPrice' ? 'bidPrice' : 'askPrice';
    }


    protected function simulatePath(array $legs, array $prices, float $startUSDT, float $fee, string $direction): array
    {
        $amount = $startUSDT;

        foreach ($legs as $leg) {

            $price = $prices[$leg['symbol']][$leg['side']];

            $amount = ($leg['side'] === 'askPrice')
                ? ($amount / $price) * (1 - $fee)
                : ($amount * $price) * (1 - $fee);
        }

        $profit = $amount - $startUSDT;

        return [
            'direction' => $direction,
            'final' => $amount,
            'profit' => $profit,
            'profit_pct' => $startUSDT > 0 ? ($profit / $startUSDT) * 100 : 0,
        ];

    }

    protected function maxQuoteAgeMs(array $prices): int
    {
        $nowMs = (int) (microtime(true) * 1000);
        $max = 0;

        foreach ($prices as $row) {

            $ts = (int) ($row['ts'] ?? 0);

            if ($ts > 0) {
                $max = max($max, $nowMs - $ts);
            }
        }
        
        return $max;
    }

    /**
     * Calculate the minimum capital that can yield a positive triangular arbitrage
     */
    protected function getMinCapital(Coin $coin, float $price): float
    {
        $symbolMeta = $this->cachedExchangeSymbol($coin->symbol);
        $filters = $symbolMeta['filters'] ?? [];

        $lotSize = collect($filters)->firstWhere('filterType', 'LOT_SIZE');
        $notional = collect($filters)->firstWhere('filterType', 'NOTIONAL');

        $stepSize = (float) $lotSize['stepSize'];
        $minNotional = (float) $notional['minNotional'];

        $capital = $minNotional;

        $precision = strlen(substr(strrchr(rtrim((string) $stepSize, '0'), '.'), 1));

        return round($capital, $precision);
    }

    protected function clampCapitalToDepth(CoinArbitrage $arb, array $prices, float $capital): float
    {
        $fraction = max(0.01, min(1.0, (float) config('binance.depth_fill_fraction', 0.25)));
        $maxByDepth = $capital;
    
        $legs = [
            [$arb->coin_one, $arb->coin_one_price],
            [$arb->coin_two, $arb->coin_two_price],
            [$arb->coin_three, $arb->coin_three_price],
        ];
    
        foreach ($legs as [$coin, $side]) {
            // Only USDT-quoted books are ~USDT notional at top of book.
            if (($coin->quote_asset ?? null) !== 'USDT') {
                continue;
            }
    
            $row = $prices[$coin->symbol] ?? null;
            if (! $row) {
                continue;
            }
    
            $cap = $side === 'askPrice'
                ? $row['askPrice'] * $row['askQty'] * $fraction
                : $row['bidPrice'] * $row['bidQty'] * $fraction;
    
            if ($cap > 0) {
                $maxByDepth = min($maxByDepth, $cap);
            }
        }
    
        return max(0.0, $maxByDepth);
    }

    protected function fetchPrices(array $symbols): ?array
    {
        $symbols = array_values(array_unique(array_filter($symbols)));
        if ($symbols === []) {
            return null;
        }
    
        try {
            $prices = app(BookTickerStore::class)->getMany($symbols);
            if ($prices === null) {
                $this->error('Missing Redis bookTicker for one or more symbols: '.implode(', ', $symbols));
    
                return null;
            }
    
            // Drop stale quotes (2s). Feed must be running.
            $nowMs = (int) (microtime(true) * 1000);
            foreach ($prices as $symbol => $row) {
                $ts = (int) ($row['ts'] ?? 0);
                if ($ts > 0 && ($nowMs - $ts) > 2000) {
                    $this->error("Stale Redis bookTicker for {$symbol}");
    
                    return null;
                }
            }
    
            return $prices;
        } catch (\Exception $e) {
            $this->error('Error reading prices from Redis: '.$e->getMessage());
    
            return null;
        }
    }

    protected function setParams(CoinArbitrage $coin_arbitrage, float $capitalUSDT): void
    {
        $trade = new Trade;
        $balances = $trade->freeBalancesMap();
    
        $coinOneSide = ($coin_arbitrage->coin_one_price === 'askPrice') ? 'BUY' : 'SELL';
        $coinOneTradeParams = $this->getTradeParams($coin_arbitrage->coin_one, $coinOneSide, $capitalUSDT, $balances);
        $coinOneTradeResponse = $trade->newOrder($coinOneTradeParams);
    
        $this->info($coinOneTradeResponse->getBody()->getContents());
    
        $coinTwoSide = ($coin_arbitrage->coin_two_price === 'askPrice') ? 'BUY' : 'SELL';
        $coinTwoTradeParams = $this->getTradeParams($coin_arbitrage->coin_two, $coinTwoSide, null, $balances);
        $coinTwoTradeResponse = $trade->newOrder($coinTwoTradeParams);
    
        $this->info($coinTwoTradeResponse->getBody()->getContents());
    
        $coinThreeSide = ($coin_arbitrage->coin_three_price === 'askPrice') ? 'BUY' : 'SELL';
        $coinThreeTradeParams = $this->getTradeParams($coin_arbitrage->coin_three, $coinThreeSide, null, $balances);
        $coinThreeTradeResponse = $trade->newOrder($coinThreeTradeParams);
    
        $this->info($coinThreeTradeResponse->getBody()->getContents());
    }

    protected function getTradeParams(Coin $coin, string $side, ?float $capitalUSDT = null, ?array $balances = null): array
    {
        $symbolMeta = $this->cachedExchangeSymbol($coin->symbol);
        $filters = $symbolMeta['filters'] ?? [];

        $lotSize = collect($filters)->firstWhere('filterType', 'LOT_SIZE');
        $stepSize = (float) $lotSize['stepSize'];
        $precision = strlen(rtrim(substr(strrchr(rtrim((string) $stepSize, '0'), '.'), 1), '.'));

        $params = [
            'symbol' => $coin->symbol,
            'type' => 'MARKET',
            'timestamp' => (new Market)->CheckServerTime(),
            'side' => $side,
        ];

        switch (true) {
            case $side === 'BUY' && $coin->quote_asset == 'USDT':
                $params['quoteOrderQty'] = number_format((float) $capitalUSDT, 2, '.', '');

                break;

            case $side === 'SELL' && $coin->quote_asset == 'USDT':
                $balance = $balances !== null
                    ? (float) ($balances[$coin->base_asset] ?? 0)
                    : (float) (new Trade)->accountInformation($coin->base_asset);
                $params['quantity'] = floor($balance / $stepSize) * $stepSize;

                break;

            default:
                $balance = $balances !== null
                    ? (float) ($balances[$coin->base_asset] ?? 0)
                    : (float) (new Trade)->accountInformation($coin->base_asset);
                $qty = floor($balance / $stepSize) * $stepSize;
                $params['quantity'] = round($qty, $precision);
        }

        return $params;
    }
}
