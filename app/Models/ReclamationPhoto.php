<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReclamationPhoto extends Model
{
  protected $fillable = ['reclamation_id','path','caption'];

  public function reclamation(): BelongsTo
  {
    return $this->belongsTo(Reclamation::class);
  }
}
