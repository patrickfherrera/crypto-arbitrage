<?php

namespace App\Services\BinanceSpotAPI;

use App\Models\Order;
use GuzzleHttp\Client;

class Wallet extends Base
{
    /**
     * Transfer asset to Funding Wallet
     *
     * @param $type
     * @param $transferAsset
     * @param $amount
     * @return void
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function userUniversalTransfer($type, $transferAsset, $amount)
    {
        $client = new Client();

        $params = [
            'type'      => $type,
            'asset'     => $transferAsset,
            'amount'    => round($amount, 2),
            'timestamp' => (new Market())->CheckServerTime(),
        ];

        $queryString = http_build_query($params);

        $client->post(config('binance.api') . '/sapi/v1/asset/transfer?' . $queryString . '&signature=' . $this->signature($queryString) , [
            'headers' => [
                'X-MBX-APIKEY' => config('binance.api_key'),
                'Content-Type' => 'application/json',
            ]
        ])->getBody()->getContents();

    }

    /**
     * @param $asset
     * @return mixed
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getFundingWallet($asset = null)
    {
        $client = new Client();

        $params = [
            'timestamp' => (new Market())->CheckServerTime(),
        ];

        if (!is_null($asset)) {
            $params['asset'] = $asset;
        }

        $queryString = http_build_query($params);

        $signature = hash_hmac('sha256', $queryString, config('binance.api_secret'));

        $response = $client->post(config('binance.api') . '/sapi/v1/asset/get-funding-asset?' . $queryString . '&signature=' . $signature , [
            'headers' => [
                'X-MBX-APIKEY' => config('binance.api_key'),
                'Content-Type' => 'application/json',
            ]
        ])->getBody()->getContents();

        return json_decode($response);

    }
}
