<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankAccount extends Model
{
    protected $fillable = [
        'name',
        'bank_code',
        'owner',
        'currency',
        'is_active',
    ];
}
