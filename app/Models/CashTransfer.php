<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashTransfer extends Model
{
    protected $fillable = [
        'project_id',
        'from_wallet_id',
        'to_wallet_id',
        'target_owner',
        'amount',
        'currency',
        'exchange_rate',
        'usd_amount',
        'status',
        'created_by',
        'accepted_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'exchange_rate' => 'decimal:6',
        'usd_amount' => 'decimal:2',
        'accepted_at' => 'datetime',
    ];

    public function fromWallet()
    {
        return $this->belongsTo(Wallet::class, 'from_wallet_id');
    }

    public function toWallet()
    {
        return $this->belongsTo(Wallet::class, 'to_wallet_id');
    }

    public function project()
    {
        return $this->belongsTo(SalesProject::class, 'project_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
