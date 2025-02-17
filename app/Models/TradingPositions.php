<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TradingPositions extends Model
{
    protected $fillable = [
        'signal_id',
        'execution_time',
        'amount',
        'status',
    ];

    public function signals()
    {
        return $this->belongsTo('Signals', 'signal_id', 'id');
    }
}
