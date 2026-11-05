<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Medication;
use App\Models\SymptomLog;
use App\Models\User;
use App\Notifications\HealthReminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_the_scheduler_sends_due_medication_and_appointment_notifications(): void
    {
        Carbon::setTestNow('2026-10-20 08:00:00');
        Notification::fake();

        $user = User::factory()->create();
        $user->forceFill([
            'sex' => 'male',
            'reminders_enabled' => true,
        ])->save();

        Medication::create([
            'user_id' => $user->id,
            'name' => 'Paracetamol',
            'dosage' => '500 mg',
            'times' => ['08:00'],
            'start_date' => now()->toDateString(),
            'is_active' => true,
            'food_instruction' => 'after_meal',
        ]);

        Appointment::create([
            'user_id' => $user->id,
            'doctor_name' => 'Dr. Cruz',
            'appointment_at' => now()->addHour(),
            'status' => 'scheduled',
        ]);

        $this->artisan('medibyte:send-reminders')->assertExitCode(0);

        Notification::assertSentToTimes($user, HealthReminder::class, 2);
    }

    public function test_reminders_carry_the_details_the_companion_card_shows(): void
    {
        Carbon::setTestNow('2026-10-20 08:00:00');
        Notification::fake();

        $user = User::factory()->create();
        $user->forceFill([
            'sex' => 'male',
            'reminders_enabled' => true,
        ])->save();

        Medication::create([
            'user_id' => $user->id,
            'name' => 'Paracetamol',
            'dosage' => '500 mg',
            'times' => ['06:00', '08:00', '20:00'],
            'start_date' => now()->toDateString(),
            'is_active' => true,
            'food_instruction' => 'after_meal',
            'notes' => 'Take with a full glass of water.',
        ]);

        Appointment::create([
            'user_id' => $user->id,
            'doctor_name' => 'Dr. Cruz',
            'specialty' => 'Dermatology',
            'hospital' => 'MediByte Clinic',
            'reason' => 'Annual skin check',
            'appointment_at' => now()->addHour(),
            'status' => 'scheduled',
        ]);

        $this->artisan('medibyte:send-reminders')->assertExitCode(0);

        Notification::assertSentTo($user, HealthReminder::class, function (HealthReminder $reminder) use ($user) {
            $payload = $reminder->toArray($user);

            return $payload['kind'] === 'medication'
                && $payload['time'] === '08:00'
                && $payload['name'] === 'Paracetamol'
                && $payload['dosage'] === '500 mg'
                && $payload['food_instruction'] === 'after_meal'
                && $payload['notes'] === 'Take with a full glass of water.'
                && $payload['schedule'] === ['06:00', '08:00', '20:00'];
        });

        Notification::assertSentTo($user, HealthReminder::class, function (HealthReminder $reminder) use ($user) {
            $payload = $reminder->toArray($user);

            return $payload['kind'] === 'appointment'
                && $payload['doctor'] === 'Dr. Cruz'
                && $payload['specialty'] === 'Dermatology'
                && $payload['hospital'] === 'MediByte Clinic'
                && $payload['reason'] === 'Annual skin check'
                && $payload['at'] === now()->addHour()->toIso8601String();
        });
    }

    public function test_the_scheduler_respects_the_reminder_switch(): void
    {
        Carbon::setTestNow('2026-10-20 08:00:00');
        Notification::fake();

        $user = User::factory()->create();
        $user->forceFill([
            'sex' => 'male',
            'reminders_enabled' => false,
        ])->save();

        Medication::create([
            'user_id' => $user->id,
            'name' => 'Paracetamol',
            'times' => ['08:00'],
            'start_date' => now()->toDateString(),
            'is_active' => true,
        ]);

        $this->artisan('medibyte:send-reminders')->assertExitCode(0);

        Notification::assertNothingSent();
    }

    public function test_doses_missed_since_the_last_sweep_are_caught_up(): void
    {
        Carbon::setTestNow('2026-10-20 08:30:00');
        Cache::flush();
        Cache::put('medibyte:reminder-sweep', now()->subHour()->timestamp);
        Notification::fake();

        $user = User::factory()->create();
        $user->forceFill([
            'sex' => 'male',
            'reminders_enabled' => true,
        ])->save();

        Medication::create([
            'user_id' => $user->id,
            'name' => 'Paracetamol',
            'times' => ['08:00'],
            'start_date' => now()->toDateString(),
            'is_active' => true,
        ]);

        $this->artisan('medibyte:send-reminders')->assertExitCode(0);

        Notification::assertSentToTimes($user, HealthReminder::class, 1);
    }

    public function test_medication_doses_still_in_the_future_are_not_sent(): void
    {
        Carbon::setTestNow('2026-10-20 08:00:00');
        Cache::flush();
        Notification::fake();

        $user = User::factory()->create();
        $user->forceFill([
            'sex' => 'male',
            'reminders_enabled' => true,
        ])->save();

        Medication::create([
            'user_id' => $user->id,
            'name' => 'Paracetamol',
            'times' => ['09:00'],
            'start_date' => now()->toDateString(),
            'is_active' => true,
        ]);

        $this->artisan('medibyte:send-reminders')->assertExitCode(0);

        Notification::assertNothingSent();
    }

    public function test_symptom_check_in_is_sent_for_recent_symptoms_not_logged_today(): void
    {
        Carbon::setTestNow('2026-10-20 10:00:00');
        Cache::flush();
        Notification::fake();

        $user = User::factory()->create();
        $user->forceFill([
            'sex' => 'male',
            'reminders_enabled' => true,
        ])->save();

        SymptomLog::create([
            'user_id' => $user->id,
            'log_date' => '2026-10-18',
            'symptom' => 'Headache',
            'severity' => 'mild',
        ]);

        $this->artisan('medibyte:send-reminders')->assertExitCode(0);

        Notification::assertSentTo(
            $user,
            HealthReminder::class,
            fn ($notification) => ($notification->toDatabase($user)['kind'] ?? null) === 'symptom'
        );
    }

    public function test_symptom_check_in_is_skipped_outside_day_hours_and_when_logged_today(): void
    {
        Carbon::setTestNow('2026-10-20 07:00:00');
        Cache::flush();
        Notification::fake();

        $user = User::factory()->create();
        $user->forceFill([
            'sex' => 'male',
            'reminders_enabled' => true,
        ])->save();

        SymptomLog::create([
            'user_id' => $user->id,
            'log_date' => '2026-10-18',
            'symptom' => 'Headache',
            'severity' => 'mild',
        ]);

        $this->artisan('medibyte:send-reminders')->assertExitCode(0);

        Notification::assertNothingSent();

        Carbon::setTestNow('2026-10-20 14:00:00');
        Cache::flush();

        SymptomLog::create([
            'user_id' => $user->id,
            'log_date' => '2026-10-20',
            'symptom' => 'Nausea',
            'severity' => 'mild',
        ]);

        $this->artisan('medibyte:send-reminders')->assertExitCode(0);

        Notification::assertNothingSent();
    }

    public function test_notifications_can_be_listed_and_marked_as_read(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['sex' => 'male'])->save();
        $user->notify(new HealthReminder([
            'dedupe_key' => 'test:1',
            'kind' => 'medication',
            'title' => 'Medication reminder',
            'body' => 'Time for Paracetamol.',
            'url' => '/medications',
        ]));

        Sanctum::actingAs($user);

        $notifications = $this->getJson('/api/v1/notifications')->assertOk()->json();
        $this->assertCount(1, $notifications);
        $this->assertNull($notifications[0]['read_at']);

        $this->postJson('/api/v1/notifications/'.$notifications[0]['id'].'/read')
            ->assertOk()
            ->assertJsonPath('message', 'Read');

        $this->assertNotNull($user->notifications()->first()->read_at);
    }

    public function test_all_notifications_can_be_marked_as_read(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['sex' => 'male'])->save();

        foreach ([1, 2] as $index) {
            $user->notify(new HealthReminder([
                'dedupe_key' => 'test:'.$index,
                'kind' => 'appointment',
                'title' => 'Upcoming appointment',
                'body' => 'Appointment reminder '.$index,
                'url' => '/appointments',
            ]));
        }

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('message', 'All read');

        $this->assertSame(0, $user->unreadNotifications()->count());
    }

    public function test_a_notification_can_be_dismissed(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['sex' => 'male'])->save();
        $user->notify(new HealthReminder([
            'dedupe_key' => 'test:dismiss',
            'kind' => 'welcome',
            'title' => 'Welcome to MediByte',
            'body' => 'Your account is ready.',
            'url' => '/profile-setup',
        ]));

        Sanctum::actingAs($user);

        $notifications = $this->getJson('/api/v1/notifications')->assertOk()->json();
        $this->assertCount(1, $notifications);

        $this->deleteJson('/api/v1/notifications/'.$notifications[0]['id'])
            ->assertOk()
            ->assertJsonPath('message', 'Dismissed');

        $this->assertSame(0, $user->notifications()->count());
    }

    public function test_an_appointment_later_in_the_week_is_announced(): void
    {
        Carbon::setTestNow('2026-10-20 08:00:00');
        Cache::flush();
        Notification::fake();

        $user = User::factory()->create();
        $user->forceFill(['sex' => 'male', 'reminders_enabled' => true])->save();

        Appointment::create([
            'user_id' => $user->id,
            'doctor_name' => 'Dr. Cruz',
            'appointment_at' => now()->addDays(3),
            'status' => 'scheduled',
        ]);

        $this->artisan('medibyte:send-reminders')->assertExitCode(0);

        Notification::assertSentTo(
            $user,
            HealthReminder::class,
            fn ($notification) => ($notification->toDatabase($user)['kind'] ?? null) === 'appointment'
        );
    }

    public function test_an_appointment_beyond_the_week_is_not_announced_yet(): void
    {
        Carbon::setTestNow('2026-10-20 08:00:00');
        Cache::flush();
        Notification::fake();

        $user = User::factory()->create();
        $user->forceFill(['sex' => 'male', 'reminders_enabled' => true])->save();

        Appointment::create([
            'user_id' => $user->id,
            'doctor_name' => 'Dr. Cruz',
            'appointment_at' => now()->addDays(10),
            'status' => 'scheduled',
        ]);

        $this->artisan('medibyte:send-reminders')->assertExitCode(0);

        Notification::assertNothingSent();
    }
}
