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

    /**
     * Free + locked balances (closer to Binance Est. Total Value).
     *
     * @return array<string, float>
     */
    public function totalBalancesMap(): array
    {
        $client = new Client;

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
            $qty = (float) $balance->free + (float) ($balance->locked ?? 0);
            if ($qty >= 1e-8) {
                $map[$balance->asset] = $qty;
            }
        }

        return $map;
    }

    /**
     * Binance dashboard Est. Total Value (USDT) across wallets.
     * GET /sapi/v1/asset/wallet/balance?quoteAsset=USDT
     */
    public function walletEstTotalUsdt(): ?float
    {
        $rows = $this->signedSapiGet('/sapi/v1/asset/wallet/balance', [
            'quoteAsset' => 'USDT',
        ]);

        if (! is_array($rows)) {
            return null;
        }

        $total = 0.0;
        foreach ($rows as $row) {
            $item = (array) $row;
            if (! ($item['activate'] ?? false)) {
                continue;
            }
            $total += (float) ($item['balance'] ?? 0);
        }

        return round($total, 8);
    }

    /**
     * Spot (free+locked) + Funding wallet assets for portfolio marking.
     *
     * @return array<string, float>
     */
    public function portfolioBalancesMap(): array
    {
        $map = $this->totalBalancesMap();

        foreach ($this->fundingBalancesMap() as $asset => $qty) {
            $map[$asset] = ($map[$asset] ?? 0) + $qty;
        }

        foreach ($this->userAssetBalancesMap() as $asset => $qty) {
            // getUserAsset can include freeze/withdrawing; take max so we don't under-count
            $map[$asset] = max($map[$asset] ?? 0, $qty);
        }

        return array_filter($map, fn ($qty) => $qty >= 1e-8);
    }

    /**
     * @return array<string, float>
     */
    public function fundingBalancesMap(): array
    {
        $rows = $this->signedSapiPost('/sapi/v1/asset/get-funding-asset', []);

        if (! is_array($rows)) {
            return [];
        }

        $map = [];
        foreach ($rows as $row) {
            $item = (array) $row;
            $asset = strtoupper((string) ($item['asset'] ?? ''));
            if ($asset === '') {
                continue;
            }
            $qty = (float) ($item['free'] ?? 0)
                + (float) ($item['locked'] ?? 0)
                + (float) ($item['freeze'] ?? 0)
                + (float) ($item['withdrawing'] ?? 0);
            if ($qty >= 1e-8) {
                $map[$asset] = ($map[$asset] ?? 0) + $qty;
            }
        }

        return $map;
    }

    /**
     * POST /sapi/v3/asset/getUserAsset — positive spot user assets.
     *
     * @return array<string, float>
     */
    public function userAssetBalancesMap(): array
    {
        $rows = $this->signedSapiPost('/sapi/v3/asset/getUserAsset', []);

        if (! is_array($rows)) {
            return [];
        }

        $map = [];
        foreach ($rows as $row) {
            $item = (array) $row;
            $asset = strtoupper((string) ($item['asset'] ?? ''));
            if ($asset === '') {
                continue;
            }
            $qty = (float) ($item['free'] ?? 0)
                + (float) ($item['locked'] ?? 0)
                + (float) ($item['freeze'] ?? 0)
                + (float) ($item['withdrawing'] ?? 0);
            if ($qty >= 1e-8) {
                $map[$asset] = $qty;
            }
        }

        return $map;
    }

    /**
     * @param  array<string, scalar|null>  $params
     * @return array<int, mixed>|null
     */
    protected function signedSapiGet(string $path, array $params = []): ?array
    {
        try {
            $params['timestamp'] = (new Market)->CheckServerTime();
            $queryString = http_build_query($params);
            $client = new Client;
            $response = $client->get(config('binance.api').$path.'?'.$queryString.'&signature='.$this->signature($queryString), [
                'headers' => [
                    'X-MBX-APIKEY' => config('binance.api_key'),
                ],
            ])->getBody()->getContents();

            $decoded = json_decode($response, true);

            return is_array($decoded) ? $decoded : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, scalar|null>  $params
     * @return array<int, mixed>|null
     */
    protected function signedSapiPost(string $path, array $params = []): ?array
    {
        try {
            $params['timestamp'] = (new Market)->CheckServerTime();
            $queryString = http_build_query($params);
            $client = new Client;
            $response = $client->post(config('binance.api').$path.'?'.$queryString.'&signature='.$this->signature($queryString), [
                'headers' => [
                    'X-MBX-APIKEY' => config('binance.api_key'),
                ],
            ])->getBody()->getContents();

            $decoded = json_decode($response, true);

            return is_array($decoded) ? $decoded : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
