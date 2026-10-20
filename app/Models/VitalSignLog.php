<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VitalSignLog extends Model
{
    protected $fillable = [
    'user_id', 'systolic', 'diastolic', 'heart_rate',
    'temperature_c', 'oxygen_saturation', 'log_date',
    ];
}
