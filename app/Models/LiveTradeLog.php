<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveTradeLog extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'capital' => 'float',
            'usdt_before' => 'float',
            'usdt_after' => 'float',
            'usdt_delta' => 'float',
            'usdt_delta_pct' => 'float',
            'equity_before' => 'float',
            'equity_after' => 'float',
            'equity_delta' => 'float',
            'equity_delta_pct' => 'float',
            'sim_profit_pct' => 'float',
            'balances_after' => 'array',
        ];
    }

    public function coinArbitrage(): BelongsTo
    {
        return $this->belongsTo(CoinArbitrage::class, 'coin_arbitrage_id');
    }
}
