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
        'telegram_group_link',
        'geo_location_link',
        'has_green_tariff',
        'electric_work_start_date',
        'panel_work_start_date',
        'inverter',
        'bms',
        'battery_name',
        'battery_qty',
        'panel_name',
        'panel_qty',
        'electrician',
        'installation_team',
        'extra_works',
        'defects_note',
        'defects_photo_path',
        'closed_at',
        'closed_by',
    ];
}
