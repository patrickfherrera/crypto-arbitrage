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

class TriangularArbitrage extends Command
{
    protected $signature = 'arbitrage:run {--interval=5} {--coin_arbitrage_id=}';

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

    public function handle(): int
    {
        $interval = max(1, (int) $this->option('interval'));
        $this->info('Starting triangular arbitrage bot');

        $coin_arbitrage = CoinArbitrage::with(['coin_one', 'coin_two', 'coin_three'])
            ->find($this->option('coin_arbitrage_id'));

        if (! $coin_arbitrage) {
            $this->error('coin_arbitrage_id not found or invalid.');

            return self::FAILURE;
        }

        $id = $coin_arbitrage->id;

        while (CoinArbitrage::query()->whereKey($id)->where('enabled', true)->exists()) {
            $this->simulate($coin_arbitrage);
            sleep($interval);
        }

        return self::SUCCESS;
    }

    protected function simulate(CoinArbitrage $coinArbitrage): void
    {
        $prices = $this->fetchPrices([
            $coinArbitrage->coin_one->symbol,
            $coinArbitrage->coin_two->symbol,
            $coinArbitrage->coin_three->symbol,
        ]);

        if (! $prices) {
            $this->error('Failed to fetch Binance prices.');

            return;
        }

        $fee = 0.001; // 0.1% per trade

        $startUSDT = $coinArbitrage->capital;

        $coinOnePrice = $prices[$coinArbitrage->coin_one->symbol][$coinArbitrage->coin_one_price];
        $coinOneAmount = ($coinArbitrage->coin_one_price === 'askPrice')
            ? ($startUSDT / $coinOnePrice) * (1 - $fee)
            : ($startUSDT * $coinOnePrice) * (1 - $fee);

        $coinTwoPrice = $prices[$coinArbitrage->coin_two->symbol][$coinArbitrage->coin_two_price];
        $coinTwoAmount = ($coinArbitrage->coin_two_price === 'askPrice')
            ? ($coinOneAmount / $coinTwoPrice) * (1 - $fee)
            : ($coinOneAmount * $coinTwoPrice) * (1 - $fee);

        $coinThreePrice = $prices[$coinArbitrage->coin_three->symbol][$coinArbitrage->coin_three_price];
        $finalUSDT = ($coinArbitrage->coin_three_price === 'askPrice')
            ? ($coinTwoAmount / $coinThreePrice) * (1 - $fee)
            : ($coinTwoAmount * $coinThreePrice) * (1 - $fee);

        $profit = $finalUSDT - $startUSDT;
        $status = $profit > 0 ? 'PROFITABLE' : 'NOT_PROFITABLE';

        ArbitrageLog::create([
            'capital' => $startUSDT,
            'final_amount' => $finalUSDT,
            'profit' => $profit,
            'status' => $status,
            'coin_arbitrage_id' => $coinArbitrage->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($profit > 0 && $coinArbitrage->test_mode == 0) {
            $this->info("✅ PROFIT: Start \${$startUSDT}, End \${$finalUSDT}, Profit = \${$profit}");
            $this->setParams($coinArbitrage);
        } else {
            $this->warn("❌ LOSS: Start \${$startUSDT}, End \${$finalUSDT}, Profit = \${$profit}");
        }
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

    protected function fetchPrices(array $symbols): ?array
    {
        $symbols = array_values(array_unique(array_filter($symbols)));
        if ($symbols === []) {
            return null;
        }

        try {
            $response = $this->binance()->get('ticker/bookTicker', [
                'symbols' => json_encode($symbols),
            ]);

            if (! $response->successful()) {
                return null;
            }

            $json = $response->json();
            if (! is_array($json)) {
                return null;
            }

            $data = collect(isset($json['symbol']) ? [$json] : $json);

            return $data->whereIn('symbol', $symbols)
                ->mapWithKeys(fn ($item) => [
                    $item['symbol'] => [
                        'bidPrice' => (float) $item['bidPrice'],
                        'askPrice' => (float) $item['askPrice'],
                    ],
                ])->toArray();
        } catch (\Exception $e) {
            $this->error('Error fetching prices: '.$e->getMessage());

            return null;
        }
    }

    protected function setParams(CoinArbitrage $coin_arbitrage): void
    {
        $trade = new Trade;
        $balances = $trade->freeBalancesMap();

        $coinOneSide = ($coin_arbitrage->coin_one_price === 'askPrice') ? 'BUY' : 'SELL';
        $coinOneTradeParams = $this->getTradeParams($coin_arbitrage->coin_one, $coinOneSide, $coin_arbitrage->capital, $balances);
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
