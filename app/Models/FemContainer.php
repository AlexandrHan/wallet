<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FemContainer extends Model
{
    protected $table = 'fem_containers';

    protected $fillable = ['date', 'name', 'amount', 'created_by'];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function payments(): HasMany
    {
        return $this->hasMany(FemContainerPayment::class, 'fem_container_id')
            ->orderBy('id', 'desc');
    }
}
