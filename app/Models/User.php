<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

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
        ];
    }

    public function profile()
    {
        return $this->hasOne(Profile::class);
    }

    public function waterLogs()
    {
        return $this->hasMany(WaterLog::class);
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
}
