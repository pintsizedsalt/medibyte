<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Profile extends Model
{
    protected $fillable = [
    'user_id', 'sex', 'period_status', 'weight_goal',
    'water_goal_ml', 'sleep_goal_hours', 'walking_goal_minutes', 'calorie_goal',
    ];

    protected $appends = ['show_period_tracker'];

    public function getShowPeriodTrackerAttribute()
    {
        return $this->sex === 'female' && $this->period_status === 'active';
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
