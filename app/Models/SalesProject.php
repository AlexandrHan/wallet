<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesProject extends Model
{
    protected $fillable = [
        'client_name',
        'total_amount',
        'advance_amount',
        'remaining_amount',
        'currency',
        'created_by',
        'status',
    ];
}
