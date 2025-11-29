<?php

namespace App\Services\BinanceSpotAPI;

class Base
{
    /**
     * Returns hashed signature required by the Trade New Order endpoint.
     *
     * @param $query_string
     * @return false|string
     */
    protected function signature($query_string)
    {
        return hash_hmac('sha256', $query_string, env('BINANCE_API_SECRET'));
    }
}
