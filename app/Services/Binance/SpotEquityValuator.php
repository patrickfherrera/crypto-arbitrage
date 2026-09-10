<?php

namespace App\Services\Binance;

use Illuminate\Support\Facades\Http;

/**
 * Mark spot balances to USDT for portfolio / live-trade equity.
 * Tries Redis book mids, then a single REST /ticker/price snapshot.
 * Pricing routes: ASSETUSDT → ASSETUSDC×USDC → ASSETBTC×BTCUSDT.
 */
class SpotEquityValuator
{
    /** @var list<string> */
    private const STABLES = ['USDT', 'USDC', 'FDUSD', 'BUSD', 'USD', 'TUSD'];

    /** @var array<string, float>|null */
    private ?array $tickerCache = null;

    /**
     * @param  array<string, float>  $balances  asset => qty
     * @return array{total: float, skipped: list<string>}
     */
    public function value(array $balances): array
    {
        $total = 0.0;
        $skipped = [];
        $needPrice = [];

        foreach ($balances as $asset => $qty) {
            $asset = strtoupper((string) $asset);
            $qty = (float) $qty;
            if ($qty < 1e-8) {
                continue;
            }

            if (in_array($asset, self::STABLES, true)) {
                $total += $qty;
                continue;
            }

            $needPrice[$asset] = $qty;
        }

        if ($needPrice === []) {
            return ['total' => round($total, 8), 'skipped' => []];
        }

        $books = $this->redisBooks(array_keys($needPrice));

        foreach ($needPrice as $asset => $qty) {
            $px = $this->priceAssetUsdt($asset, $books);
            if ($px <= 0) {
                $skipped[] = $asset;
                continue;
            }
            $total += $qty * $px;
        }

        return ['total' => round($total, 8), 'skipped' => $skipped];
    }

    public function totalUsdt(array $balances): float
    {
        return $this->value($balances)['total'];
    }

    /**
     * @param  list<string>  $assets
     * @return array<string, array{bidPrice: float, askPrice: float}>
     */
    protected function redisBooks(array $assets): array
    {
        $symbols = [];
        foreach ($assets as $asset) {
            $symbols[] = $asset.'USDT';
            $symbols[] = $asset.'USDC';
            $symbols[] = $asset.'BTC';
        }
        $symbols[] = 'BTCUSDT';
        $symbols[] = 'USDCUSDT';

        $store = app(BookTickerStore::class);
        $out = [];
        foreach (array_unique($symbols) as $symbol) {
            $one = $store->getMany([$symbol]);
            if ($one !== null) {
                $out[$symbol] = $one[$symbol];
            }
        }

        return $out;
    }

    /**
     * @param  array<string, array{bidPrice: float, askPrice: float}>  $books
     */
    protected function priceAssetUsdt(string $asset, array $books): float
    {
        $asset = strtoupper($asset);

        $direct = $this->midOrTicker($asset.'USDT', $books);
        if ($direct > 0) {
            return $direct;
        }

        $usdc = $this->midOrTicker($asset.'USDC', $books);
        if ($usdc > 0) {
            $usdcUsdt = $this->midOrTicker('USDCUSDT', $books);
            if ($usdcUsdt <= 0) {
                $usdcUsdt = 1.0;
            }

            return $usdc * $usdcUsdt;
        }

        $btc = $this->midOrTicker($asset.'BTC', $books);
        if ($btc > 0) {
            $btcUsdt = $this->midOrTicker('BTCUSDT', $books);
            if ($btcUsdt > 0) {
                return $btc * $btcUsdt;
            }
        }

        return 0.0;
    }

    /**
     * @param  array<string, array{bidPrice: float, askPrice: float}>  $books
     */
    protected function midOrTicker(string $symbol, array $books): float
    {
        $symbol = strtoupper($symbol);
        $row = $books[$symbol] ?? null;
        if ($row) {
            $bid = (float) ($row['bidPrice'] ?? 0);
            $ask = (float) ($row['askPrice'] ?? 0);
            if ($bid > 0 && $ask > 0) {
                return ($bid + $ask) / 2;
            }
            $mid = max($bid, $ask);
            if ($mid > 0) {
                return $mid;
            }
        }

        return $this->tickerPrice($symbol);
    }

    protected function tickerPrice(string $symbol): float
    {
        $tickers = $this->allTickerPrices();

        return (float) ($tickers[strtoupper($symbol)] ?? 0);
    }

    /**
     * One REST call for the whole universe; cached for this valuator instance.
     *
     * @return array<string, float>
     */
    protected function allTickerPrices(): array
    {
        if ($this->tickerCache !== null) {
            return $this->tickerCache;
        }

        $this->tickerCache = [];

        try {
            $response = Http::timeout(20)
                ->baseUrl(rtrim((string) config('binance.api'), '/'))
                ->get('/api/v3/ticker/price');

            if (! $response->successful()) {
                return $this->tickerCache;
            }

            foreach ($response->json() ?? [] as $row) {
                if (! isset($row['symbol'], $row['price'])) {
                    continue;
                }
                $this->tickerCache[strtoupper($row['symbol'])] = (float) $row['price'];
            }
        } catch (\Throwable) {
            // leave empty; callers treat as unpriced
        }

        return $this->tickerCache;
    }
}
