<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class CoinArbitrage extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    public function coin_one(): HasOne
    {
        return $this->hasOne(Coin::class, 'id', 'coin_one_id');
    }

    public function coin_two(): HasOne
    {
        return $this->hasOne(Coin::class, 'id', 'coin_two_id');
    }

    public function coin_three(): HasOne
    {
        return $this->hasOne(Coin::class, 'id', 'coin_three_id');
    }
}
