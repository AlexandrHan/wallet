<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reclamation extends Model
{
    protected $fillable = [
        'code','reported_at','last_name','city','phone','problem',
        'has_loaner','loaner_ordered','serial_number','status','created_by'
    ];

    protected $casts = [
        'reported_at' => 'date',
        'has_loaner' => 'boolean',
        'loaner_ordered' => 'boolean',
    ];

    public function steps(): HasMany
    {
        return $this->hasMany(ReclamationStep::class);
    }

    public function step(string $key): ?ReclamationStep
    {
        return $this->steps->firstWhere('step_key', $key);
    }
}
