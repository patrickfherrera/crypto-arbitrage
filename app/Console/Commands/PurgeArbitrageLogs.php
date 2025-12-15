<?php

namespace App\Console\Commands;

use App\Models\ArbitrageLog;
use Carbon\Carbon;
use Illuminate\Console\Command;

class PurgeArbitrageLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:purge-arbitrage-logs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Deletes rows from the arbitrage logs table older than a month';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        ArbitrageLog::where('created_at', '<=', Carbon::now()->subMonth())->forceDelete();
    }
}
