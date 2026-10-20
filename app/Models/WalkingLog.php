<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WalkingLog extends Model
{
    protected $fillable = ['user_id', 'activity_type', 'steps', 'minutes', 'log_date'];
}