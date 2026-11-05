<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
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
    'appointment_reminders_enabled',
    'appointment_lead_minutes',
    'appointment_day_notice',
    'appointment_repeat_minutes',
    'medication_reminders_enabled',
    'medication_lead_minutes',
    'medication_repeat_minutes',
    'onboarding_completed_at',
    'consented_at',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Every lead time a person may ask for ahead of an appointment, keyed by the
     * minutes before it and labelled the way the Settings page shows it. The
     * booked notice is the first key because it is what an account receives
     * before anyone has chosen anything.
     *
     * @var array<int, string>
     */
    public const APPOINTMENT_LEADS = [
        10080 => 'As soon as it is booked',
        1440 => '1 day before',
        180 => '3 hours before',
        120 => '2 hours before',
        60 => '1 hour before',
        30 => '30 minutes before',
        0 => 'At the appointment time',
    ];

    /**
     * @var array<int, string>
     */
    public const MEDICATION_LEADS = [
        30 => '30 minutes before',
        15 => '15 minutes before',
        5 => '5 minutes before',
        0 => 'At the scheduled time',
    ];

    /**
     * How often a reminder may repeat itself while it is still unopened.
     *
     * @var array<int, string>
     */
    public const REPEAT_INTERVALS = [
        15 => 'Every 15 minutes',
        30 => 'Every 30 minutes',
        60 => 'Every hour',
        120 => 'Every 2 hours',
    ];

    /**
     * @var array<int, int>
     */
    public const DEFAULT_APPOINTMENT_LEADS = [10080];

    /**
     * @var array<int, int>
     */
    public const DEFAULT_MEDICATION_LEADS = [0];

    /**
     * The hour an appointment day notice waits for, so a person is told about
     * today's appointments with their morning rather than at 2am.
     */
    public const DAY_NOTICE_HOUR = 8;

    protected $appends = ['show_period_tracker', 'avatar_url', 'reminder_preferences'];

    /**
     * Both reminder switches are on until a person says otherwise. The columns carry
     * the same defaults, and spelling them here as well keeps a freshly built user —
     * before it has been read back from the database — from looking like a person who
     * turned their reminders off.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'appointment_reminders_enabled' => true,
        'appointment_day_notice' => false,
        'medication_reminders_enabled' => true,
    ];

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
            'appointment_reminders_enabled' => 'boolean',
            'appointment_lead_minutes' => 'array',
            'appointment_day_notice' => 'boolean',
            'appointment_repeat_minutes' => 'integer',
            'medication_reminders_enabled' => 'boolean',
            'medication_lead_minutes' => 'array',
            'medication_repeat_minutes' => 'integer',
            'onboarding_completed_at' => 'datetime',
            'consented_at' => 'datetime',
        ];
    }

    public function getShowPeriodTrackerAttribute(): bool
    {
        // Only users who menstruate should see the cycle tracker.
        return $this->sex === 'female' && $this->period_status === 'active';
    }

    /**
     * The reminder choices as the Settings page and the scheduler both read them:
     * a null choice falls back to what this account was already getting.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getReminderPreferencesAttribute(): array
    {
        return [
            'appointment' => [
                'enabled' => (bool) $this->appointment_reminders_enabled,
                'leads' => $this->appointmentLeads(),
                'day_notice' => (bool) $this->appointment_day_notice,
                'repeat_minutes' => $this->appointmentRepeat(),
            ],
            'medication' => [
                'enabled' => (bool) $this->medication_reminders_enabled,
                'leads' => $this->medicationLeads(),
                'repeat_minutes' => $this->medicationRepeat(),
            ],
        ];
    }

    /**
     * The appointment notices this person asked for, shortest lead first.
     *
     * @return array<int, int>
     */
    public function appointmentLeads(): array
    {
        return $this->chosenLeads($this->appointment_lead_minutes, array_keys(self::APPOINTMENT_LEADS), self::DEFAULT_APPOINTMENT_LEADS);
    }

    /**
     * The medication notices this person asked for, shortest lead first.
     *
     * @return array<int, int>
     */
    public function medicationLeads(): array
    {
        return $this->chosenLeads($this->medication_lead_minutes, array_keys(self::MEDICATION_LEADS), self::DEFAULT_MEDICATION_LEADS);
    }

    public function appointmentRepeat(): ?int
    {
        return $this->chosenRepeat($this->appointment_repeat_minutes);
    }

    public function medicationRepeat(): ?int
    {
        return $this->chosenRepeat($this->medication_repeat_minutes);
    }

    /**
     * @param  array<int, int>  $allowed
     * @param  array<int, int>  $fallback
     * @return array<int, int>
     */
    private function chosenLeads(mixed $stored, array $allowed, array $fallback): array
    {
        if ($stored === null) {
            return $fallback;
        }

        $chosen = array_map('intval', (array) $stored);

        $leads = array_values(array_filter($allowed, fn (int $minutes) => in_array($minutes, $chosen, true)));

        // Shortest lead first, which is the order the notices actually arrive.
        sort($leads);

        return $leads;
    }

    private function chosenRepeat(mixed $minutes): ?int
    {
        $minutes = $minutes === null ? null : (int) $minutes;

        return $minutes !== null && in_array($minutes, array_keys(self::REPEAT_INTERVALS), true) ? $minutes : null;
    }

    /**
     * Address of the uploaded photo, cache-busted from the file's own timestamp.
     * A new crop overwrites the same path, which leaves updated_at untouched, so
     * the record could never prove the photo changed.
     */
    public function getAvatarUrlAttribute(): ?string
    {
        if (! $this->avatar_path) {
            return null;
        }

        $version = Storage::disk('local')->lastModified($this->avatar_path)
            ?: $this->updated_at?->getTimestamp()
            ?: time();

        return '/avatar?v='.$version;
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

    public function customMoods()
    {
        return $this->hasMany(CustomMood::class);
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
