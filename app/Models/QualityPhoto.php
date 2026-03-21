<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QualityPhoto extends Model
{
    protected $fillable = ['quality_check_id', 'file_path'];

    public function qualityCheck(): BelongsTo
    {
        return $this->belongsTo(QualityCheck::class, 'quality_check_id');
    }
}
