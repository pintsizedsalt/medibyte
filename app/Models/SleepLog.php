<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SleepLog extends Model
{
    protected $fillable = ['user_id', 'hours', 'bedtime', 'wake_time', 'log_date'];
}