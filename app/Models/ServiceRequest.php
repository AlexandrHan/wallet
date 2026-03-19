<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceRequest extends Model
{
    protected $fillable = [
        'client_name',
        'settlement',
        'phone_number',
        'telegram_group_link',
        'geo_location_link',
        'electrician',
        'installation_team',
        'is_urgent',
        'description',
        'scheduled_date',
        'created_by',
        'status',
    ];
}
