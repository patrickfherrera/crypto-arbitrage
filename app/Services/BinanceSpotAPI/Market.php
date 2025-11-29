<?php

namespace App\Services\BinanceSpotAPI;

use App\Models\Coin;
use GuzzleHttp\Client;

class Market
{
    /**
     * Returns current sever timestamp of Binance
     *
     * @return mixed
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function CheckServerTime()
    {
        $client = new Client();

        $response = $client->get(env("BINANCE_API") . '/api/v3/time', [
            'headers' => [
                'Content-Type' => 'application/json',
            ]
        ])->getBody()->getContents();

        return json_decode($response)->serverTime;
    }

    /**
     * Sends request to Binance to get the current value of the coin.
     *
     * @param Coin $coin
     * @return mixed
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function symbolPriceTicker(Coin $coin)
    {
        $client = new Client();

        $response = $client->get(env("BINANCE_API") . '/api/v3/ticker/price?symbol=' . $coin->symbol, [
            'headers' => [
                'Content-Type' => 'application/json',
            ]
        ])->getBody()->getContents();

        return json_decode($response)->price;
    }

    /**
     * Sends request to Binance to get price changes for the past 24 hours
     * @param Coin $coin
     * @return mixed
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function TwentyFourHrTickerPriceChangeStatistics(Coin $coin)
    {
        $client = new Client();

        $response = $client->get(env("BINANCE_API") . '/api/v3/ticker/24hr?symbol=' . $coin->symbol, [
            'headers' => [
                'X-MBX-APIKEY' => env('BINANCE_API_KEY'),
                'Content-Type' => 'application/json',
            ]
        ])->getBody()->getContents();

        return json_decode($response);
    }
}
