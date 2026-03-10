<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReclamationStep extends Model
{
    protected $fillable = ['reclamation_id','step_key','done_date','note','ttn','files'];

    protected $casts = [
        'done_date' => 'date',
        'files' => 'array',
    ];

    public function reclamation(): BelongsTo
    {
        return $this->belongsTo(Reclamation::class);
    }
}
