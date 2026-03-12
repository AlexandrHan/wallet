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
        'is_retail',
        'created_by',
        'lead_manager_user_id',
        'status',
        'source_layer',
        'telegram_group_link',
        'geo_location_link',
        'phone_number',
        'has_green_tariff',
        'electric_work_start_date',
        'electric_work_days',
        'panel_work_start_date',
        'panel_work_days',
        'inverter',
        'delivered_inverter',
        'bms',
        'delivered_bms',
        'battery_name',
        'battery_qty',
        'delivered_battery',
        'panel_name',
        'panel_qty',
        'delivered_panels',
        'electrician',
        'electrician_note',
        'electrician_task_note',
        'installation_team',
        'installation_team_note',
        'installation_team_task_note',
        'extra_works',
        'defects_note',
        'defects_photo_path',
        'closed_at',
        'closed_by',
    ];
}
