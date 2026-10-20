<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyLog extends Model
{
    protected $fillable = ['user_id', 'log_date', 'water_ml'];
    protected $casts = ['log_date' => 'date:Y-m-d'];
}
