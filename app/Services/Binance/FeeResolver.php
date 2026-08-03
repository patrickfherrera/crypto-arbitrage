<?php

namespace App\Services\Binance;

use App\Services\BinanceSpotAPI\Market;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;

class FeeResolver
{
    /**
     * Account taker fee as a fraction (e.g. 0.00075). Cached 1h.
     */
    public function takerFee(): float
    {
        $fallback = (float) config('binance.taker_fee', 0.001);

        if (! config('binance.use_account_fee', true)) {
            return $fallback;
        }

        return (float) Cache::remember('binance.account.taker_fee', 3600, function () use ($fallback) {
            try {
                $params = ['timestamp' => (new Market)->CheckServerTime()];
                $queryString = http_build_query($params);
                $signature = hash_hmac('sha256', $queryString, config('binance.api_secret'));

                $response = (new Client)->get(
                    rtrim(config('binance.api'), '/').'/api/v3/account?'.$queryString.'&signature='.$signature,
                    ['headers' => ['X-MBX-APIKEY' => config('binance.api_key')]]
                );

                $json = json_decode($response->getBody()->getContents(), true);
                $taker = $json['commissionRates']['taker'] ?? null;

                return $taker !== null ? (float) $taker : $fallback;
            } catch (\Throwable) {
                return $fallback;
            }
        });
    }
}