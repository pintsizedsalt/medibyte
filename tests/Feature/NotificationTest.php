<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Medication;
use App\Models\User;
use App\Notifications\HealthReminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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
}
