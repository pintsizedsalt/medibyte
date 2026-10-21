<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'name',
    'first_name',
    'last_name',
    'username',
    'email',
    'password',
    'sex',
    'period_status',
    'weight_goal',
    'water_goal_ml',
    'sleep_goal_hours',
    'walking_goal_steps',
    'calorie_goal',
    'cycle_length',
    'reminders_enabled',
    'onboarding_completed_at',
    'consented_at',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $appends = ['show_period_tracker'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'reminders_enabled' => 'boolean',
            'onboarding_completed_at' => 'datetime',
            'consented_at' => 'datetime',
        ];
    }

    public function getShowPeriodTrackerAttribute(): bool
    {
        // Only users who menstruate should see the cycle tracker.
        return $this->sex === 'female' && $this->period_status === 'active';
    }

    public function dailyLogs()
    {
        return $this->hasMany(DailyLog::class);
    }

    public function sleepLogs()
    {
        return $this->hasMany(SleepLog::class);
    }

    public function walkingLogs()
    {
        return $this->hasMany(WalkingLog::class);
    }

    public function mealLogs()
    {
        return $this->hasMany(MealLog::class);
    }

    public function weightLogs()
    {
        return $this->hasMany(WeightLog::class);
    }

    public function moodLogs()
    {
        return $this->hasMany(MoodLog::class);
    }

    public function periodLogs()
    {
        return $this->hasMany(PeriodLog::class);
    }

    public function symptomLogs()
    {
        return $this->hasMany(SymptomLog::class);
    }

    public function vitalSignLogs()
    {
        return $this->hasMany(VitalSignLog::class);
    }

    public function medications()
    {
        return $this->hasMany(Medication::class);
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    public function documents()
    {
        return $this->hasMany(Document::class);
    }
}
