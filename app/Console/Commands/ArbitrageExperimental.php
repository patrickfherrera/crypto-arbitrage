<?php

namespace App\Console\Commands;

use App\Models\CoinArbitrage;
use App\Models\ProfitValue;
//use App\Services\BinanceSpotAPI\Convert;
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
    protected $signature = 'app:arbitrage-experimental {--coin_arbitrage_id=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * @var int
     */
    protected $count = 0;

    protected $rate_limit = 86400;

    private function convertCoin($coinConvertParams)
    {dd('stop');
        $convertCoinSendQuoteResponse = (new Convert())->sendQuote($coinConvertParams);

        $this->count += 1;

        $this->info($this->count);

        sleep(1);

        if ($convertCoinSendQuoteResponse instanceof ClientException) {
            $this->info(json_encode($coinConvertParams));
            $this->info($convertCoinSendQuoteResponse->getMessage());
            exit();
        }

        $decodedCoinSendQuoteResponse = json_decode($convertCoinSendQuoteResponse->getBody()->getContents(), true);

        if (!Arr::has($decodedCoinSendQuoteResponse, 'quoteId')) {
            exit();
        }

        $this->info($decodedCoinSendQuoteResponse['quoteId']);

        return $decodedCoinSendQuoteResponse;

    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $coin_arbitrage = CoinArbitrage::with(['coin_one', 'coin_two', 'coin_three'])->find($this->option('coin_arbitrage_id'));

        do {

            $coin_one = $coin_arbitrage->coin_one;
            $coin_one_from_asset = $coin_arbitrage->coin_one_from_asset;
            $coin_one_to_asset = $coin_arbitrage->coin_one_to_asset;

            $coinOneConvertParams = [
                'fromAsset' => $coin_one->$coin_one_from_asset,
                'toAsset' => $coin_one->$coin_one_to_asset,
                'toAmount' => round($coin_arbitrage->quantity, 8)
            ];

            $coinOneQuoteConvertResponse = $this->convertCoin($coinOneConvertParams);

            $coin_two = $coin_arbitrage->coin_two;
            $coin_two_from_asset = $coin_arbitrage->coin_two_from_asset;
            $coin_two_to_asset = $coin_arbitrage->coin_two_to_asset;

            $coinTwoConvertParams = [
                'fromAsset' => $coin_two->$coin_two_from_asset,
                'toAsset' => $coin_two->$coin_two_to_asset,
                'fromAmount' => $coinOneQuoteConvertResponse['toAmount']
            ];

            $coinTwoQuoteConvertResponse = $this->convertCoin($coinTwoConvertParams);

            $coin_three = $coin_arbitrage->coin_three;
            $coin_three_from_asset = $coin_arbitrage->coin_three_from_asset;
            $coin_three_to_asset = $coin_arbitrage->coin_three_to_asset;

            $coinThreeConvertSendQuoteParams = [
                'fromAsset' => $coin_three->$coin_three_from_asset,
                'toAsset' => $coin_three->$coin_three_to_asset,
                'fromAmount' => $coinTwoQuoteConvertResponse['toAmount']
            ];

            $coinThreeQuoteConvertResponse = $this->convertCoin($coinThreeConvertSendQuoteParams);

            $initialUsdtValue = $coinOneQuoteConvertResponse['fromAmount'];
            $finalUsdtValue = $coinThreeQuoteConvertResponse['toAmount'];

            $profit = $finalUsdtValue - $initialUsdtValue;

            if ($profit > 0) {
                $convertCoinOneAcceptParams = [
                    'quoteId' => $coinOneQuoteConvertResponse['quoteId']
                ];

                (new Convert())->acceptQuote($convertCoinOneAcceptParams);

                $convertCoinTwoAcceptParams = [
                    'quoteId' => $coinTwoQuoteConvertResponse['quoteId']
                ];

                (new Convert())->acceptQuote($convertCoinTwoAcceptParams);

                $convertCoinThreeAcceptParams = [
                    'quoteId' => $coinThreeQuoteConvertResponse['quoteId']
                ];

                (new Convert())->acceptQuote($convertCoinThreeAcceptParams);
            }

            ProfitValue::create([
                'value' => $profit,
                'initial_usdt_value' => $initialUsdtValue,
                'final_usdt_value' => $finalUsdtValue,
                'coin_arbitrage_id' => $coin_arbitrage->id,
                'coin_arbitrage_profit' => $coin_arbitrage->profit,
                'coin_one_quote_response' => json_encode($coinOneQuoteConvertResponse),
                'coin_two_quote_response' => json_encode($coinTwoQuoteConvertResponse),
                'coin_three_quote_response' => json_encode($coinThreeQuoteConvertResponse),
            ]);

        } while ($this->count <= $this->rate_limit);
    }
}
