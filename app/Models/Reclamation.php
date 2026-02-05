<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reclamation extends Model
{
  protected $fillable = [
    'opened_at','last_name','settlement','phone',
    'has_replacement_fund','need_order_replacement',
    'serial_number',
    'dismantled_at','sent_to_service_at','sent_to_service_ttn',
    'service_received_at','repaired_sent_back_at','repaired_sent_back_ttn',
    'installed_at',
    'replacement_sent_at','replacement_return_to','replacement_return_ttn',
    'closed_at','status','note',
  ];

  protected $casts = [
    'opened_at' => 'date',
    'dismantled_at' => 'date',
    'sent_to_service_at' => 'date',
    'service_received_at' => 'date',
    'repaired_sent_back_at' => 'date',
    'installed_at' => 'date',
    'replacement_sent_at' => 'date',
    'closed_at' => 'date',
    'has_replacement_fund' => 'boolean',
    'need_order_replacement' => 'boolean',
  ];

  public function photos(): HasMany
  {
    return $this->hasMany(ReclamationPhoto::class);
  }
}
