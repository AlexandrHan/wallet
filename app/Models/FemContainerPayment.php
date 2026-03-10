<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FemContainerPayment extends Model
{
    protected $table = 'fem_container_payments';

    protected $fillable = ['fem_container_id', 'paid_at', 'amount', 'created_by'];

    protected $casts = [
        'paid_at' => 'date',
        'amount' => 'decimal:2',
    ];

    public function container(): BelongsTo
    {
        return $this->belongsTo(FemContainer::class, 'fem_container_id');
    }
}
