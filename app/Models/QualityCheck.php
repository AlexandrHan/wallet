<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QualityCheck extends Model
{
    protected $fillable = [
        'project_id', 'created_by', 'approved_by',
        'status', 'deficiencies', 'approved_at',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(SalesProject::class, 'project_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(QualityPhoto::class, 'quality_check_id');
    }
}
