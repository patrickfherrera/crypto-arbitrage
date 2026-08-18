<?php

namespace App\Console\Commands;

use App\Models\ArbitrageLog;
use App\Models\Coin;
use App\Models\CoinArbitrage;
use App\Services\Binance\FeeResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class ScanUsdtTriangles extends Command
{
    protected $signature = 'arbitrage:scan-triangles
                            {--quote=USDT}
                            {--capital=100}
                            {--top=30}
                            {--cull-days=7 : Lookback days when culling historical losers}
                            {--seed : Create enabled CoinArbitrage rows for top hits (test_mode=1)}';

    protected $description = 'Enumerate quote triangles from exchangeInfo and score via REST bookTicker';


    /** @var array<string, array{base: string, quote: string}> */
    protected array $pairs = [];

    public function handle(FeeResolver $fees): int
    {
        $quote = strtoupper((string) $this->option('quote'));
        $capital = (float) $this->option('capital');
        $fee = $fees->takerFee();

        $this->info("Fetching exchangeInfo… fee={$fee}");

        $response = Http::timeout(60)->get(rtrim(config('binance.api'), '/').'/api/v3/exchangeInfo');
        if (! $response->successful()) {
            $this->error('exchangeInfo failed');

            return self::FAILURE;
        }

        $pairs = []; // SYMBOL => [base, quote]
        foreach ($response->json('symbols') ?? [] as $s) {
            if (($s['status'] ?? '') !== 'TRADING' || ($s['isSpotTradingAllowed'] ?? false) !== true) {
                continue;
            }
            $pairs[strtoupper($s['symbol'])] = [
                'base' => strtoupper($s['baseAsset']),
                'quote' => strtoupper($s['quoteAsset']),
            ];
        }

        $this->pairs = $pairs;

        // quote-pairs: BASEQUOTE where quote = USDT → base asset
        $quoteBases = [];
        foreach ($pairs as $symbol => $p) {
            if ($p['quote'] === $quote) {
                $quoteBases[$p['base']] = $symbol;
            }
        }

        $bases = array_keys($quoteBases);
        $triangles = [];

        for ($i = 0; $i < count($bases); $i++) {
            for ($j = $i + 1; $j < count($bases); $j++) {
                $a = $bases[$i];
                $b = $bases[$j];
                $ab = null;
                $abSide = null; // how to go A→B on cross

                if (isset($pairs[$a.$b])) {
                    $ab = $a.$b; // sell A for B? base=A quote=B → bid sells A→B
                } elseif (isset($pairs[$b.$a])) {
                    $ab = $b.$a;
                } else {
                    continue;
                }

                $symQuoteA = $quoteBases[$a];
                $symQuoteB = $quoteBases[$b];
                $triangles[] = [$symQuoteA, $ab, $symQuoteB, $a, $b];
            }
        }

        $this->info('Triangles found: '.count($triangles));

        $this->info('Fetching all bookTickers via REST…');
        $allPrices = $this->fetchAllBookTickers();
        $this->info('Book tickers loaded: '.count($allPrices));
        
        $scored = [];

        foreach ($triangles as [$s1, $s2, $s3, $a, $b]) {
            if (! isset($allPrices[$s1], $allPrices[$s2], $allPrices[$s3])) {
                continue;
            }
            $prices = [
                $s1 => $allPrices[$s1],
                $s2 => $allPrices[$s2],
                $s3 => $allPrices[$s3],
            ];

            // Path forward: USDT→A (buy A), A→B on cross, B→USDT (sell B)
            $fwd = $this->scoreUsdtTriangle($prices, $s1, $s2, $s3, $a, $b, $capital, $fee, 'forward');
            $rev = $this->scoreUsdtTriangle($prices, $s1, $s2, $s3, $a, $b, $capital, $fee, 'reverse');
            // Prefer reverse on ties / near-ties (paper wins are reverse-dominated).
            $best = $rev['profit_pct'] >= $fwd['profit_pct'] - 0.01 ? $rev : $fwd;

            $scored[] = $best + [
                'symbols' => [$s1, $s2, $s3],
                'assets' => [$a, $b, $quote],
            ];
        }

        usort($scored, function ($x, $y) {
            $cmp = $y['profit_pct'] <=> $x['profit_pct'];
            if ($cmp !== 0) {
                return $cmp;
            }

            // Stable: reverse before forward on equal pct
            return ($y['direction'] === 'reverse' ? 1 : 0) <=> ($x['direction'] === 'reverse' ? 1 : 0);
        });

        $top = array_slice($scored, 0, (int) $this->option('top'));
        $this->table(
            ['pct', 'dir', 'path', 'final'],
            array_map(fn ($r) => [
                number_format($r['profit_pct'], 4),
                $r['direction'],
                implode(' → ', $r['symbols']),
                number_format($r['final'], 4),
            ], $top)
        );

        if ($this->option('seed')) {
            CoinArbitrage::query()->update(['enabled' => 0]);

            $seedIds = [];
            foreach ($top as $row) {
                $arb = $this->seedRow($row, $capital);
                if ($arb) {
                    $seedIds[] = $arb->id;
                }
            }

            if ($seedIds !== []) {
                CoinArbitrage::query()->whereIn('id', $seedIds)->update(['enabled' => 1]);
            }

            $culled = $this->cullHistoricalLosers((int) $this->option('cull-days'), $seedIds);
            if ($culled > 0) {
                $this->warn("Culled {$culled} historical loser(s) from enabled set.");
            }

            Cache::put('binance.feed.reload', true);
            $this->info('Disabled others; enabled '.count($seedIds).' of top '.(int) $this->option('top').' (test_mode=1); feed reload requested.');
        }

        return self::SUCCESS;
    }

    /**
     * Disable seeded rows that historically never win and bleed in the lookback window.
     *
     * @param  array<int, int>  $seedIds
     */
    protected function cullHistoricalLosers(int $days, array $seedIds): int
    {
        if ($days <= 0 || $seedIds === []) {
            return 0;
        }

        $since = now()->subDays($days);
        $culled = 0;

        foreach ($seedIds as $id) {
            $stats = ArbitrageLog::query()
                ->where('coin_arbitrage_id', $id)
                ->where('created_at', '>=', $since)
                ->whereNotNull('profit_pct')
                ->selectRaw('COUNT(*) as total, SUM(CASE WHEN profit_pct > 0 AND status = ? THEN 1 ELSE 0 END) as wins, AVG(profit_pct) as mean_pct', ['PROFITABLE'])
                ->first();

            $total = (int) ($stats->total ?? 0);
            $wins = (int) ($stats->wins ?? 0);
            $mean = $stats->mean_pct !== null ? (float) $stats->mean_pct : null;

            // Need enough history; zero wins + deeply negative mean → disable.
            if ($total >= 100 && $wins === 0 && $mean !== null && $mean < -0.10) {
                CoinArbitrage::query()->whereKey($id)->update(['enabled' => 0]);
                $culled++;
                $this->line("  cull #{$id}: rows={$total} wins=0 mean=".number_format($mean, 4).'%');
            }
        }

        return $culled;
    }

    protected function scoreUsdtTriangle(
        array $prices,
        string $sQuoteA,
        string $sCross,
        string $sQuoteB,
        string $a,
        string $b,
        float $capital,
        float $fee,
        string $direction
    ): array {
        // forward: buy A with USDT, cross A→B, sell B to USDT
        // reverse: buy B with USDT, cross B→A, sell A to USDT
        if ($direction === 'forward') {
            $legs = $this->buildLegs($prices, $sQuoteA, $sCross, $sQuoteB, $a, $b, true);
        } else {
            $legs = $this->buildLegs($prices, $sQuoteB, $sCross, $sQuoteA, $b, $a, false);
        }

        $amount = $capital;
        foreach ($legs as $leg) {
            $px = $prices[$leg['symbol']][$leg['side']];
            $amount = $leg['side'] === 'askPrice'
                ? ($amount / $px) * (1 - $fee)
                : ($amount * $px) * (1 - $fee);
        }

        $profit = $amount - $capital;

        return [
            'direction' => $direction,
            'final' => $amount,
            'profit' => $profit,
            'profit_pct' => $capital > 0 ? ($profit / $capital) * 100 : 0,
            'symbols' => array_column($legs, 'symbol'),
            'legs' => $legs,
        ];
    }

    /**
     * Build 3 legs starting/ending in quote asset.
     */
    protected function buildLegs(
        array $prices,
        string $buySym,
        string $crossSym,
        string $sellSym,
        string $fromAsset,
        string $toAsset,
        bool $forward
    ): array {
        $leg1 = ['symbol' => $buySym, 'side' => 'askPrice'];
    
        $cross = $this->pairs[$crossSym] ?? null;
        if ($cross && $cross['base'] === $fromAsset && $cross['quote'] === $toAsset) {
            $leg2 = ['symbol' => $crossSym, 'side' => 'bidPrice']; // sell from → to
        } elseif ($cross && $cross['base'] === $toAsset && $cross['quote'] === $fromAsset) {
            $leg2 = ['symbol' => $crossSym, 'side' => 'askPrice']; // buy to with from
        } else {
            // fallback
            $leg2 = ['symbol' => $crossSym, 'side' => 'bidPrice'];
        }
    
        $leg3 = ['symbol' => $sellSym, 'side' => 'bidPrice'];
    
        return [$leg1, $leg2, $leg3];
    }

    protected function seedRow(array $row, float $capital): ?CoinArbitrage
    {
        $syms = $row['symbols'];
        $sides = array_column($row['legs'], 'side');
        $coins = [];

        foreach ($syms as $symbol) {
            $meta = $this->pairs[$symbol] ?? null;
            if (! $meta) {
                $this->warn("Skip seed; missing pair meta for {$symbol}");

                return null;
            }

            $coin = Coin::query()->where('symbol', $symbol)->first();

            if (! $coin) {
                $coin = new Coin([
                    'base_asset' => $meta['base'],
                    'quote_asset' => $meta['quote'],
                ]);
                // Coin::creating sets symbol = base+quote
                $coin->save();
            }

            $coins[] = $coin;
        }

        $arb = CoinArbitrage::firstOrCreate(
            [
                'coin_one_id' => $coins[0]->id,
                'coin_two_id' => $coins[1]->id,
                'coin_three_id' => $coins[2]->id,
            ],
            [
                'coin_one_price' => $sides[0] ?? 'askPrice',
                'coin_two_price' => $sides[1] ?? 'bidPrice',
                'coin_three_price' => $sides[2] ?? 'bidPrice',
                'profit' => 0,
                'capital' => $capital,
                'test_mode' => 1,
                'enabled' => 0,
            ]
        );

        $arb->fill([
            'coin_one_price' => $sides[0] ?? 'askPrice',
            'coin_two_price' => $sides[1] ?? 'bidPrice',
            'coin_three_price' => $sides[2] ?? 'bidPrice',
            'capital' => $capital,
            'test_mode' => 1,
            'enabled' => 1,
        ])->save();

        return $arb;
    }

    /**
     * @return array<string, array{bidPrice: float, askPrice: float}>
     */
    protected function fetchAllBookTickers(): array
    {
        $response = Http::timeout(60)->get(rtrim(config('binance.api'), '/').'/api/v3/ticker/bookTicker');
        if (! $response->successful()) {
            return [];
        }

        $out = [];
        foreach ($response->json() ?? [] as $row) {
            if (! isset($row['symbol'], $row['bidPrice'], $row['askPrice'])) {
                continue;
            }
            $sym = strtoupper($row['symbol']);
            $out[$sym] = [
                'bidPrice' => (float) $row['bidPrice'],
                'askPrice' => (float) $row['askPrice'],
            ];
        }

        return $out;
    }
}