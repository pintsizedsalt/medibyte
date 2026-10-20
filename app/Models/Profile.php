<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Profile extends Model
{
    protected $fillable = [
    'user_id', 'sex', 'period_status', 'weight_goal',
    'water_goal_ml', 'sleep_goal_hours', 'walking_goal_steps', 'calorie_goal',
    'cycle_length', 'reminders_enabled',
    ];

    protected $appends = ['show_period_tracker'];

    protected $casts = [
        'reminders_enabled' => 'boolean',
    ];

    public function getShowPeriodTrackerAttribute()
    {
        return $this->sex === 'female' && $this->period_status === 'active';
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
