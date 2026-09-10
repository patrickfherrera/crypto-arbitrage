<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WalletEquitySnapshot extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'est_total_usdt' => 'float',
            'marked_usdt' => 'float',
            'usdt_cash' => 'float',
            'skipped' => 'array',
        ];
    }
}
