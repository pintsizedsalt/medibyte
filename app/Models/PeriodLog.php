<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PeriodLog extends Model
{
    protected $fillable = ['user_id', 'start_date', 'end_date', 'flow', 'notes'];

    protected $casts = [
    'start_date' => 'date:Y-m-d',
    'end_date' => 'date:Y-m-d',
    ];
}
