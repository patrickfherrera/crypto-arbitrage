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
        $configuredCapital = (float) $coinArbitrage->capital;
        $mode = (string) config('binance.direction_mode', 'prefer_reverse');

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

        [$forward, $reverse] = $this->scoreDirections(
            $coinArbitrage,
            $prices,
            $configuredCapital,
            $fee,
            $forwardLegs,
            $reverseLegs,
            $mode
        );

        $best = $this->pickBestDirection($forward, $reverse, $mode);

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

            [$forward, $reverse] = $this->scoreDirections(
                $coinArbitrage,
                $confirmedPrices,
                $configuredCapital,
                $fee,
                $forwardLegs,
                $reverseLegs,
                $mode
            );

            $best = $this->pickBestDirection($forward, $reverse, $mode);
            $prices = $confirmedPrices;

            if ($best['profit_pct'] <= 0) {
                $this->warn("confirm-green: faded to {$best['profit_pct']}%");
            } else {
                $this->info("confirm-green: still {$best['profit_pct']}%");
            }
        }

        $quoteAgeMs = $this->maxQuoteAgeMs($prices);
        $maxAge = (int) config('binance.max_quote_age_ms', 300);
        $startUSDT = (float) $best['capital'];
        $pct = number_format($best['profit_pct'], 4);
        $minLogPct = (float) config('binance.log_min_profit_pct', -0.02);

        if ($best['profit_pct'] <= $minLogPct) {
            $this->warn("skip {$best['direction']} {$pct}% (below {$minLogPct}%) age={$quoteAgeMs}ms");

            return;
        }

        // Stale greens: log as not actionable (NOT_PROFITABLE) so paper stats stay honest.
        $staleGreen = $best['profit_pct'] > 0 && $maxAge > 0 && $quoteAgeMs > $maxAge;
        if ($staleGreen) {
            $this->warn("stale-green {$best['direction']} {$pct}% age={$quoteAgeMs}ms > {$maxAge}ms");
        }

        $isGreen = $best['profit'] > 0 && ! $staleGreen;

        ArbitrageLog::create([
            'capital' => $startUSDT,
            'final_amount' => $best['final'],
            'profit' => $best['profit'],
            'profit_pct' => $best['profit_pct'],
            'status' => $isGreen ? 'PROFITABLE' : 'NOT_PROFITABLE',
            'direction' => $best['direction'],
            'quote_age_ms' => $quoteAgeMs,
            'coin_arbitrage_id' => $coinArbitrage->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $minExecute = (float) config('binance.min_execute_profit_pct', 0.05);
        $minCapital = (float) config('binance.min_execute_capital', 3);

        if (
            $isGreen
            && $best['profit_pct'] >= $minExecute
            && $startUSDT >= $minCapital
            && (int) $coinArbitrage->test_mode === 0
        ) {
            $this->info("✅ EXECUTE {$best['direction']} {$pct}% cap={$startUSDT} age={$quoteAgeMs}ms");
            $this->setParams($coinArbitrage, $startUSDT, $best['direction']);
        } else {
            $this->warn("❌ {$best['direction']} {$pct}% profit={$best['profit']} cap={$startUSDT} age={$quoteAgeMs}ms");
        }
    }

    /**
     * @param  array<int, array{symbol: string, side: string}>  $forwardLegs
     * @param  array<int, array{symbol: string, side: string}>  $reverseLegs
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    protected function scoreDirections(
        CoinArbitrage $coinArbitrage,
        array $prices,
        float $configuredCapital,
        float $fee,
        array $forwardLegs,
        array $reverseLegs,
        string $mode
    ): array {
        $capFwd = $this->clampCapitalToDepth($coinArbitrage, $prices, $configuredCapital, 'forward');
        $capRev = $this->clampCapitalToDepth($coinArbitrage, $prices, $configuredCapital, 'reverse');

        $forward = $mode === 'reverse_only' || $capFwd <= 0
            ? $this->emptyPath('forward')
            : $this->simulatePath($forwardLegs, $prices, $capFwd, $fee, 'forward');

        $reverse = $mode === 'forward_only' || $capRev <= 0
            ? $this->emptyPath('reverse')
            : $this->simulatePath($reverseLegs, $prices, $capRev, $fee, 'reverse');

        return [$forward, $reverse];
    }

    /**
     * @param  array<string, mixed>  $forward
     * @param  array<string, mixed>  $reverse
     * @return array<string, mixed>
     */
    protected function pickBestDirection(array $forward, array $reverse, string $mode): array
    {
        return match ($mode) {
            'reverse_only' => $reverse,
            'forward_only' => $forward,
            // Ties and near-ties go reverse (all paper wins were reverse).
            'prefer_reverse' => $reverse['profit_pct'] >= $forward['profit_pct'] - 0.01
                ? $reverse
                : $forward,
            default => $forward['profit_pct'] >= $reverse['profit_pct'] ? $forward : $reverse,
        };
    }

    /**
     * @return array{direction: string, final: float, profit: float, profit_pct: float, capital: float}
     */
    protected function emptyPath(string $direction): array
    {
        return [
            'direction' => $direction,
            'final' => 0.0,
            'profit' => 0.0,
            'profit_pct' => -999.0,
            'capital' => 0.0,
        ];
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
            'capital' => $startUSDT,
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

    protected function clampCapitalToDepth(CoinArbitrage $arb, array $prices, float $capital, string $direction = 'forward'): float
    {
        $fraction = max(0.01, min(1.0, (float) config('binance.depth_fill_fraction', 0.25)));
        $maxByDepth = $capital;

        if ($direction === 'reverse') {
            $legs = [
                [$arb->coin_three, $this->flipSide($arb->coin_three_price)],
                [$arb->coin_two, $this->flipSide($arb->coin_two_price)],
                [$arb->coin_one, $this->flipSide($arb->coin_one_price)],
            ];
        } else {
            $legs = [
                [$arb->coin_one, $arb->coin_one_price],
                [$arb->coin_two, $arb->coin_two_price],
                [$arb->coin_three, $arb->coin_three_price],
            ];
        }

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

    protected function setParams(CoinArbitrage $coin_arbitrage, float $capitalUSDT, string $direction = 'forward'): void
    {
        $trade = new Trade;
        $balances = $trade->freeBalancesMap();

        if ($direction === 'reverse') {
            $legs = [
                [$coin_arbitrage->coin_three, $this->flipSide($coin_arbitrage->coin_three_price)],
                [$coin_arbitrage->coin_two, $this->flipSide($coin_arbitrage->coin_two_price)],
                [$coin_arbitrage->coin_one, $this->flipSide($coin_arbitrage->coin_one_price)],
            ];
        } else {
            $legs = [
                [$coin_arbitrage->coin_one, $coin_arbitrage->coin_one_price],
                [$coin_arbitrage->coin_two, $coin_arbitrage->coin_two_price],
                [$coin_arbitrage->coin_three, $coin_arbitrage->coin_three_price],
            ];
        }

        foreach ($legs as $index => [$coin, $priceSide]) {
            if ($index > 0) {
                $balances = $trade->freeBalancesMap();
            }

            $side = $priceSide === 'askPrice' ? 'BUY' : 'SELL';
            $legCapital = $index === 0 ? $capitalUSDT : null;
            $params = $this->getTradeParams($coin, $side, $legCapital, $balances);
            $response = $trade->newOrder($params);
            $this->info($response->getBody()->getContents());
        }
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
