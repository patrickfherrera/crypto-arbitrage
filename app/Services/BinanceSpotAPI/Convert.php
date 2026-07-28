<?php

namespace App\Services\BinanceSpotAPI;

use App\Models\Coin;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;

class Convert extends Base
{
    /**
     * API used to send convert quote.
     *
     * @param $params
     * @return \Exception|ClientException|\Psr\Http\Message\ResponseInterface
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function sendQuote($params)
    {
        try {

            $params['timestamp'] = (new Market())->CheckServerTime();

            $queryString = http_build_query($params);

            $client = new Client();

            return $client->post(config('binance.api') . config('binance.send_quote_convert_url') . $queryString . '&signature=' . $this->signature($queryString), [
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
     * API used to execute convert quote.
     *
     * @param $params
     * @return \Exception|ClientException|\Psr\Http\Message\ResponseInterface
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function acceptQuote($params)
    {
        try {

            $params['timestamp'] = (new Market())->CheckServerTime();

            $queryString = http_build_query($params);

            $client = new Client();

            return $client->post(config('binance.api') . config('binance.accept_quote_convert_url')  . $queryString . '&signature=' . $this->signature($queryString) , [
                'headers' => [
                    'X-MBX-APIKEY' => config('binance.api_key'),
                    'Content-Type' => 'application/json',
                ]
            ]);

        } catch (ClientException $exception) {

            return $exception;
        }

    }
}
