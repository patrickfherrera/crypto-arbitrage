<?php

namespace App\Services\Binance;

use Illuminate\Support\Facades\Redis;

class BookTickerStore
{
    public const KEY_PREFIX = 'binance:book:';

    public function put(string $symbol, float $bid, float $ask): void
    {
        $symbol = strtoupper($symbol);

        Redis::set(self::KEY_PREFIX.$symbol, json_encode([
            'symbol' => $symbol,
            'bidPrice' => $bid,
            'askPrice' => $ask,
            'ts' => (int) (microtime(true) * 1000),
        ]));
    }

    /**
     * @param  list<string>  $symbols
     * @return array<string, array{bidPrice: float, askPrice: float, ts?: int}>|null
     */
    public function getMany(array $symbols): ?array
    {
        $out = [];

        foreach ($symbols as $symbol) {
            $symbol = strtoupper($symbol);
            $raw = Redis::get(self::KEY_PREFIX.$symbol);

            if (! is_string($raw) || $raw === '') {
                return null;
            }

            $decoded = json_decode($raw, true);
            if (! is_array($decoded) || ! isset($decoded['bidPrice'], $decoded['askPrice'])) {
                return null;
            }

            $out[$symbol] = [
                'bidPrice' => (float) $decoded['bidPrice'],
                'askPrice' => (float) $decoded['askPrice'],
                'ts' => (int) ($decoded['ts'] ?? 0),
            ];
        }

        return $out;
    }
}