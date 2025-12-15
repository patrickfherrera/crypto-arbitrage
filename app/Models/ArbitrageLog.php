<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class ArbitrageLog extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $guarded = ['id'];

    public function coin_arbitrage(): HasOne
    {
        return $this->hasOne(CoinArbitrage::class, 'id', 'coin_arbitrage_id');
    }
}
