<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalaryPenalty extends Model
{
    protected $fillable = [
        'staff_group',
        'staff_name',
        'year',
        'month',
        'amount',
        'description',
        'sort_order',
        'created_by',
    ];
}
