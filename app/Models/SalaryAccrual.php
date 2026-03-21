<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalaryAccrual extends Model
{
    protected $fillable = [
        'project_id', 'user_id', 'staff_group', 'staff_name',
        'amount', 'currency', 'details', 'status',
        'paid_by', 'paid_at', 'entry_id',
    ];

    protected $casts = [
        'amount'  => 'float',
        'paid_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(SalesProject::class, 'project_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function paidByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }
}
