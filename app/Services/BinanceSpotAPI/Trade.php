<?php

namespace App\Services\BinanceSpotAPI;

use App\Models\Coin;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;

class Trade extends Base
{
    /**
     * BUY or SELL coin in Binance
     *
     * @param $params
     * @return \Exception|ClientException|\Psr\Http\Message\ResponseInterface
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function newOrder($params)
    {
        try {

            $params['timestamp'] = (new Market())->CheckServerTime();

            $queryString = http_build_query($params);

            $client = new Client();

            return $client->post(config('binance.api') . config('binance.order_url') . $queryString . '&signature=' . $this->signature($queryString) , [
                'headers' => [
                    'X-MBX-APIKEY' => config('binance.api_key'),
                    'Content-Type' => 'application/json',
                ]
            ]);

        } catch (ClientException $exception) {

            return $exception;
        }

    }

    /**
     * @param $coin_asset
     * @return float|void
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function accountInformation($coin_asset)
    {
        $client = new Client();

        $params = [
            'timestamp' => (new Market())->CheckServerTime(),
        ];

        $queryString = http_build_query($params);

        $response = $client->get(config('binance.api') . '/api/v3/account?' . $queryString . '&signature=' . $this->signature($queryString) , [
            'headers' => [
                'X-MBX-APIKEY' => config('binance.api_key'),
                'Content-Type' => 'application/json',
            ]
        ])->getBody()->getContents();

        $decodedResponse = json_decode($response);

        foreach ($decodedResponse->balances as $balance) {
            if ($balance->asset == $coin_asset) {
                return doubleval($balance->free);
            }
        }
    }

    /**
     * One signed /api/v3/account call; all free balances (for batching trade prep).
     *
     * @return array<string, float>
     */
    public function freeBalancesMap(): array
    {
        $client = new Client();

        $params = [
            'timestamp' => (new Market)->CheckServerTime(),
        ];

        $queryString = http_build_query($params);

        $response = $client->get(config('binance.api').'/api/v3/account?'.$queryString.'&signature='.$this->signature($queryString), [
            'headers' => [
                'X-MBX-APIKEY' => config('binance.api_key'),
                'Content-Type' => 'application/json',
            ],
        ])->getBody()->getContents();

        $decodedResponse = json_decode($response);

        if (! isset($decodedResponse->balances) || ! is_array($decodedResponse->balances)) {
            return [];
        }

        $map = [];
        foreach ($decodedResponse->balances as $balance) {
            $map[$balance->asset] = (float) $balance->free;
        }

        return $map;
    }
}
