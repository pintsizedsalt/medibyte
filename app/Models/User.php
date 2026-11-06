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
    'cycle_reminders_enabled',
    'cycle_lead_days',
    'cycle_reminder_time',
    'cycle_fertile_reminder',
    'cycle_ovulation_reminder',
    'cycle_pms_reminder',
    'cycle_late_reminder',
    'cycle_daily_log_reminder',
    'cycle_daily_log_time',
    'cycle_pill_reminder',
    'cycle_pill_time',
    'cycle_discreet',
    'cycle_paused',
    'cycle_pause_reason',
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
     * Reminders may be set further ahead than the choices above, so each feature
     * also takes a custom lead time written in minutes: anywhere from a minute to
     * a month ahead of an appointment, and from a minute to a day ahead of a dose.
     * A number outside that window is a mistyped amount rather than a reminder.
     *
     * @var array<string, int>
     */
    public const CUSTOM_LEAD_CEILINGS = [
        'appointment' => 43200,
        'medication' => 1440,
    ];

    /**
     * @var int
     */
    public const MAX_CUSTOM_LEADS = 5;

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

    /**
     * How far ahead of a predicted period the cycle card may speak up, keyed by
     * the number of days before it and labelled the way the Settings page shows it.
     *
     * @var array<int, string>
     */
    public const CYCLE_LEAD_DAYS = [
        1 => '1 day before',
        2 => '2 days before',
        3 => '3 days before',
        7 => '1 week before',
    ];

    public const DEFAULT_CYCLE_LEAD_DAYS = 2;

    public const DEFAULT_CYCLE_REMINDER_TIME = '08:00';

    /**
     * The reasons a person may pause cycle predictions, e.g. while pregnant or on
     * hormonal birth control. Kept as free text so the page can offer these while
     * still accepting anything a doctor might phrase differently.
     */
    public const CYCLE_PAUSE_REASONS = [
        'pregnancy',
        'postpartum',
        'breastfeeding',
        'hormonal_birth_control',
        'menopause',
        'other',
    ];

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
        'cycle_reminders_enabled' => true,
        'cycle_lead_days' => self::DEFAULT_CYCLE_LEAD_DAYS,
        'cycle_reminder_time' => self::DEFAULT_CYCLE_REMINDER_TIME,
        'cycle_late_reminder' => true,
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
            'cycle_reminders_enabled' => 'boolean',
            'cycle_lead_days' => 'integer',
            'cycle_fertile_reminder' => 'boolean',
            'cycle_ovulation_reminder' => 'boolean',
            'cycle_pms_reminder' => 'boolean',
            'cycle_late_reminder' => 'boolean',
            'cycle_daily_log_reminder' => 'boolean',
            'cycle_pill_reminder' => 'boolean',
            'cycle_discreet' => 'boolean',
            'cycle_paused' => 'boolean',
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
            'cycle' => [
                'enabled' => (bool) $this->cycle_reminders_enabled,
                'lead_days' => $this->cycleLeadDays(),
                'time' => $this->cycleReminderTime(),
                'fertile' => (bool) $this->cycle_fertile_reminder,
                'ovulation' => (bool) $this->cycle_ovulation_reminder,
                'pms' => (bool) $this->cycle_pms_reminder,
                'late' => (bool) $this->cycle_late_reminder,
                'daily_log' => (bool) $this->cycle_daily_log_reminder,
                'daily_log_time' => $this->cycle_daily_log_time,
                'pill' => (bool) $this->cycle_pill_reminder,
                'pill_time' => $this->cycle_pill_time,
                'discreet' => (bool) $this->cycle_discreet,
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
        return $this->chosenLeads(
            $this->appointment_lead_minutes,
            array_keys(self::APPOINTMENT_LEADS),
            self::DEFAULT_APPOINTMENT_LEADS,
            self::CUSTOM_LEAD_CEILINGS['appointment'],
        );
    }

    /**
     * The medication notices this person asked for, shortest lead first.
     *
     * @return array<int, int>
     */
    public function medicationLeads(): array
    {
        return $this->chosenLeads(
            $this->medication_lead_minutes,
            array_keys(self::MEDICATION_LEADS),
            self::DEFAULT_MEDICATION_LEADS,
            self::CUSTOM_LEAD_CEILINGS['medication'],
        );
    }

    public function appointmentRepeat(): ?int
    {
        return $this->chosenRepeat($this->appointment_repeat_minutes);
    }

    public function medicationRepeat(): ?int
    {
        return $this->chosenRepeat($this->medication_repeat_minutes);
    }

    /** How many days before a predicted period the cycle card speaks up. */
    public function cycleLeadDays(): int
    {
        return in_array($this->cycle_lead_days, array_keys(self::CYCLE_LEAD_DAYS), true)
            ? (int) $this->cycle_lead_days
            : self::DEFAULT_CYCLE_LEAD_DAYS;
    }

    /** The time of day the cycle notice waits for, falling back to 8am. */
    public function cycleReminderTime(): string
    {
        return preg_match('/^\d{2}:\d{2}$/', (string) $this->cycle_reminder_time)
            ? $this->cycle_reminder_time
            : self::DEFAULT_CYCLE_REMINDER_TIME;
    }

    /**
     * The notices this person asked for, shortest lead first. A value is kept when it
     * is one of the offered choices or a custom lead inside the feature's own window,
     * so an amount typed in by hand survives alongside the presets.
     *
     * @param  array<int, int>  $allowed
     * @param  array<int, int>  $fallback
     * @return array<int, int>
     */
    private function chosenLeads(mixed $stored, array $allowed, array $fallback, int $ceiling): array
    {
        if ($stored === null) {
            return $fallback;
        }

        $chosen = array_map('intval', (array) $stored);

        $leads = array_values(array_unique(array_filter(
            $chosen,
            fn (int $minutes) => in_array($minutes, $allowed, true) || ($minutes >= 1 && $minutes <= $ceiling)
        )));

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

    public function periodDays()
    {
        return $this->hasMany(PeriodDay::class);
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
