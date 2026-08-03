<?php

namespace App\Console\Commands;

use App\Models\CoinArbitrage;
use App\Services\Binance\BookTickerStore;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use WebSocket\Client;
use WebSocket\ConnectionException;

class BinanceBookTickerFeed extends Command
{
    protected $signature = 'binance:book-ticker-feed
                            {--symbols= : Comma-separated symbols (default: enabled CoinArbitrage pairs)}
                            {--base-url=wss://stream.binance.com:9443}';

    protected $description = 'Stream Binance bookTicker into Redis';

    public function handle(BookTickerStore $store): int
    {
        while (true) {
            $symbols = $this->resolveSymbols();
            if ($symbols === []) {
                $this->warn('No enabled symbols; retrying in 5s...');
                sleep(5);
                continue;
            }

            $this->info('Subscribing: '.implode(', ', $symbols));

            try {
                $this->runStream($symbols, $store);
            } catch (\Throwable $e) {
                if ($e->getMessage() === 'reload') {
                    $this->info('Reload requested; resubscribing...');
                    continue;
                }
                $this->error('WS error: '.$e->getMessage());
                $this->warn('Reconnecting in 3s...');
                sleep(3);
            }
        }
    }

    /**
     * @param  list<string>  $symbols
     */
    protected function runStream(array $symbols, BookTickerStore $store): void
    {
        $streams = implode('/', array_map(
            fn (string $s) => strtolower($s).'@bookTicker',
            $symbols
        ));

        $url = rtrim($this->option('base-url'), '/').'/stream?streams='.$streams;

        $client = new Client($url, [
            'timeout' => 60,
        ]);

        $this->info('Connected: '.$url);

        while (true) {
            if (Cache::pull('binance.feed.reload')) {
                $client->close();
                throw new \RuntimeException('reload');
            }

            try {
                $raw = $client->receive();
            } catch (ConnectionException $e) {
                $client->close();
                throw $e;
            }

            if (! is_string($raw) || $raw === '') {
                continue;
            }

            $payload = json_decode($raw, true);
            if (! is_array($payload)) {
                continue;
            }

            $data = $payload['data'] ?? $payload;
            if (! isset($data['s'], $data['b'], $data['a'])) {
                continue;
            }

            $store->put(
                (string) $data['s'],
                (float) $data['b'],
                (float) $data['a'],
                (float) ($data['B'] ?? 0),
                (float) ($data['A'] ?? 0)
            );
        }
    }

    /**
     * @return list<string>
     */
    protected function resolveSymbols(): array
    {
        if ($opt = $this->option('symbols')) {
            return array_values(array_unique(array_filter(array_map(
                fn ($s) => strtoupper(trim($s)),
                explode(',', $opt)
            ))));
        }

        $symbols = [];

        CoinArbitrage::query()
            ->where('enabled', true)
            ->with(['coin_one', 'coin_two', 'coin_three'])
            ->each(function (CoinArbitrage $row) use (&$symbols) {
                foreach (['coin_one', 'coin_two', 'coin_three'] as $rel) {
                    if ($row->{$rel}?->symbol) {
                        $symbols[] = strtoupper($row->{$rel}->symbol);
                    }
                }
            });

        return array_values(array_unique($symbols));
    }
}