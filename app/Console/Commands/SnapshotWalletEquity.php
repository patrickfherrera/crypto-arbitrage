<?php

namespace App\Console\Commands;

use App\Models\WalletEquitySnapshot;
use App\Services\Binance\SpotEquityValuator;
use App\Services\BinanceSpotAPI\Trade;
use Illuminate\Console\Command;

class SnapshotWalletEquity extends Command
{
    protected $signature = 'wallet:snapshot-equity';

    protected $description = 'Record Binance Est. Total Value (and marked portfolio) for the portfolio chart';

    public function handle(SpotEquityValuator $valuator): int
    {
        try {
            $trade = new Trade;
            $estTotal = $trade->walletEstTotalUsdt();
            $balances = $trade->portfolioBalancesMap();
            $valued = $valuator->value($balances);

            $snap = WalletEquitySnapshot::create([
                'est_total_usdt' => $estTotal,
                'marked_usdt' => $valued['total'],
                'usdt_cash' => (float) ($balances['USDT'] ?? 0),
                'skipped' => $valued['skipped'] !== [] ? $valued['skipped'] : null,
            ]);

            $this->info(sprintf(
                'Snapshot #%d est=%s marked=%s usdt=%s',
                $snap->id,
                $snap->est_total_usdt !== null ? number_format($snap->est_total_usdt, 4) : 'null',
                number_format((float) $snap->marked_usdt, 4),
                number_format((float) $snap->usdt_cash, 4)
            ));

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            report($e);

            return self::FAILURE;
        }
    }
}
