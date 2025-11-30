<?php

namespace App\Console\Commands;

use App\Models\ArbitrageLog;
use App\Models\Coin;
use App\Models\CoinArbitrage;
use App\Services\BinanceSpotAPI\Market;
use App\Services\BinanceSpotAPI\Trade;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TriangularArbitrage extends Command
{
    protected $signature = 'arbitrage:run {--interval=5} {--coin_arbitrage_id=}';
    protected $description = 'Run triangular arbitrage simulation 24/7 and log results to DB';

    protected $apiUrl = 'https://api.binance.com/api/v3';

    public function handle()
    {
        $interval = (int)$this->option('interval'); // seconds between checks
        $this->info("Starting triangular arbitrage bot");

        $coin_arbitrage = CoinArbitrage::with(['coin_one', 'coin_two', 'coin_three'])
            ->find($this->option('coin_arbitrage_id'));

        while ($coin_arbitrage->enabled) {
            $this->simulate($coin_arbitrage);
            sleep($interval); // wait before next check
        }
    }

    protected function simulate(CoinArbitrage $coinArbitrage): void
    {
        $prices = $this->fetchPrices([
            $coinArbitrage->coin_one->symbol,
            $coinArbitrage->coin_two->symbol,
            $coinArbitrage->coin_three->symbol,
        ]);

        if (!$prices) {
            $this->error("Failed to fetch Binance prices.");
            return;
        }

        $fee = 0.001; // 0.1% per trade

        // Step 1: Determine minimum starting USDT for a valid DOGE purchase
        $startUSDT = $coinArbitrage->capital; //$this->getMinCapital($coinArbitrage->coin_one, $prices[$coinArbitrage->coin_one->symbol][$coinArbitrage->coin_one_price]);

        // Step 2: Simulate triangular trade with this capital
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

        // Save to DB
        ArbitrageLog::create([
            'capital'           => $startUSDT,
            'final_amount'      => $finalUSDT,
            'profit'            => $profit,
            'status'            => $status,
            'coin_arbitrage_id' => $coinArbitrage->id,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        // Print to console
        if ($profit > 0 && $coinArbitrage->test_mode == 0) {
            $this->info("✅ PROFIT: Start \${$startUSDT}, End \${$finalUSDT}, Profit = \${$profit}");
            $this->setParams($coinArbitrage, $prices);
        } else {
            $this->warn("❌ LOSS: Start \${$startUSDT}, End \${$finalUSDT}, Profit = \${$profit}");
        }
    }

    /**
     * Calculate the minimum capital that can yield a positive triangular arbitrage
     */
    protected function getMinCapital(Coin $coin, float $price): float
    {
        $response = Http::get("https://api.binance.com/api/v3/exchangeInfo", [
            "symbol" => $coin->symbol
        ]);
        $info = $response->json();
        $filters = $info['symbols'][0]['filters'];

        $lotSize   = collect($filters)->firstWhere('filterType', 'LOT_SIZE');
        $notional  = collect($filters)->firstWhere('filterType', 'NOTIONAL');

        $stepSize     = (float) $lotSize['stepSize'];
        $minNotional  = (float) $notional['minNotional'];

        // Start with Binance minimum notional
        $capital = $minNotional;

        // Round up to nearest step size for quantity
        $precision = strlen(substr(strrchr(rtrim($stepSize, '0'), "."), 1));

        return round($capital, $precision);
    }


    protected function fetchPrices(array $symbols)
    {
        try {
            $response = Http::get($this->apiUrl . '/ticker/bookTicker');
            $data = collect($response->json());

            return $data->whereIn('symbol', $symbols)
                ->mapWithKeys(fn($item) => [
                    $item['symbol'] => [
                        'bidPrice' => (float)$item['bidPrice'],
                        'askPrice' => (float)$item['askPrice'],
                    ]
                ])->toArray();

        } catch (\Exception $e) {
            $this->error("Error fetching prices: " . $e->getMessage());
            return null;
        }
    }

    protected function setParams(CoinArbitrage $coin_arbitrage)
    {
        $coinOneSide = ($coin_arbitrage->coin_one_price === 'askPrice') ? 'BUY' : 'SELL';
        $coinOneTradeParams = $this->getTradeParams($coin_arbitrage->coin_one, $coinOneSide, $coin_arbitrage->capital);
        $coinOneTradeResponse = (new Trade())->newOrder($coinOneTradeParams);

        $this->info($coinOneTradeResponse->getBody()->getContents());

        $coinTwoSide = ($coin_arbitrage->coin_two_price === 'askPrice') ? 'BUY' : 'SELL';
        $coinTwoTradeParams = $this->getTradeParams($coin_arbitrage->coin_two, $coinTwoSide);
        $coinTwoTradeResponse = (new Trade())->newOrder($coinTwoTradeParams);

        $this->info($coinTwoTradeResponse->getBody()->getContents());

        $coinThreeSide = ($coin_arbitrage->coin_three_price === 'askPrice') ? 'BUY' : 'SELL';
        $coinThreeTradeParams = $this->getTradeParams($coin_arbitrage->coin_three, $coinThreeSide);
        $coinThreeTradeResponse = (new Trade())->newOrder($coinThreeTradeParams);

        $this->info($coinThreeTradeResponse->getBody()->getContents());
    }

    protected function getTradeParams(Coin $coin, $side, $capitalUSDT = null)
    {
        // Get exchange info for symbol
        $response = Http::get("https://api.binance.com/api/v3/exchangeInfo", [
            "symbol" => $coin->symbol
        ]);
        $info = $response->json();

        $filters = $info['symbols'][0]['filters'];

        $lotSize   = collect($filters)->firstWhere('filterType', 'LOT_SIZE');
        $stepSize  = (float) $lotSize['stepSize'];
        $precision = strlen(rtrim(substr(strrchr(rtrim($stepSize, '0'), "."), 1), ".")); // decimals

        $params = [
            'symbol'    => $coin->symbol,
            'type'      => 'MARKET',
            'timestamp' => (new Market())->CheckServerTime(),
            'side'      => $side,
        ];

        switch (true) {
            case $side === 'BUY' && $coin->quote_asset == 'USDT';
                // ✅ Use quoteOrderQty for BUY orders when USDT is the quote
                $params['quoteOrderQty'] = number_format($capitalUSDT, 2, '.', '');

                break;

            case $side === 'SELL' && $coin->quote_asset == 'USDT';

                // ✅ Use quantity for SELL orders USDT pairs
                $balance = (new Trade())->accountInformation($coin->base_asset); // query via /api/v3/account
                $params['quantity'] = floor($balance / $stepSize) * $stepSize;
                break;

            default:
                // ✅ Use quantity for SELL orders (or non-USDT pairs)
                $balance = (new Trade())->accountInformation($coin->base_asset); // query via /api/v3/account
                $qty = floor($balance / $stepSize) * $stepSize; // round down to stepSize
                $params['quantity'] = round($qty, $precision);
        }

        return $params;
    }
}
