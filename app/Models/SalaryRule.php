<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalaryRule extends Model
{
    protected $fillable = [
        'staff_group',
        'staff_name',
        'mode',
        'currency',
        'fixed_amount',
        'commission_percent',
        'piecework_unit_rate',
        'foreman_bonus',
        'piecework_grid_le_50',
        'piecework_grid_gt_50',
        'piecework_hybrid_le_50',
        'piecework_hybrid_gt_50',
        'created_by',
    ];
}
