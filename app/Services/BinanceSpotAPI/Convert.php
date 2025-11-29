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

            return $client->post(env("BINANCE_API") . env("BINANCE_API_SEND_QUOTE_CONVERT_URL") . $queryString . '&signature=' . $this->signature($queryString), [
                'headers' => [
                    'X-MBX-APIKEY' => env('BINANCE_API_KEY'),
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

            return $client->post(env("BINANCE_API") . env("BINANCE_API_ACCEPT_QUOTE_CONVERT_URL")  . $queryString . '&signature=' . $this->signature($queryString) , [
                'headers' => [
                    'X-MBX-APIKEY' => env('BINANCE_API_KEY'),
                    'Content-Type' => 'application/json',
                ]
            ]);

        } catch (ClientException $exception) {

            return $exception;
        }

    }
}
