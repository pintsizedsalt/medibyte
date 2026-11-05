<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Medication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Reminders are judged by the notification rows a person ends up holding: one for each
 * moment they asked for, nothing repeated, and nothing that comes back once it has
 * been opened or thrown away.
 */
class ReminderSchedulerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_each_chosen_appointment_lead_arrives_at_its_own_moment(): void
    {
        $user = $this->track(['appointment_lead_minutes' => [1440, 60]]);

        $this->appoint($user, '2026-10-21 09:00');

        $this->sweep('2026-10-20 09:00:00');
        $this->assertSame(['appointment:1:2026-10-21 09:00:lead1440'], $this->keys($user));

        $this->sweep('2026-10-20 09:05:00');
        $this->assertCount(1, $this->keys($user));

        $this->sweep('2026-10-21 08:00:00');
        $this->assertSame([
            'appointment:1:2026-10-21 09:00:lead1440',
            'appointment:1:2026-10-21 09:00:lead60',
        ], $this->keys($user));
    }

    public function test_a_visit_missed_while_the_app_was_closed_is_caught_up_once(): void
    {
        $user = $this->track(['appointment_lead_minutes' => [1440, 60, 0]]);

        $this->appoint($user, '2026-10-21 08:00');

        $this->sweep('2026-10-20 09:00:00');
        $this->sweep('2026-10-21 08:00:00');

        $this->assertSame([
            'appointment:1:2026-10-21 08:00:lead0',
            'appointment:1:2026-10-21 08:00:lead1440',
        ], $this->keys($user));
    }

    public function test_an_appointment_notice_carries_the_visit_details_a_clinic_needs(): void
    {
        $user = $this->track(['appointment_lead_minutes' => [60]]);

        Appointment::create([
            'user_id' => $user->id,
            'doctor_name' => 'Dr. Cruz',
            'specialty' => 'Dermatology',
            'hospital' => 'MediByte Clinic',
            'reason' => 'Annual skin check',
            'appointment_at' => '2026-10-20 09:00',
            'status' => 'scheduled',
        ]);

        $this->sweep('2026-10-20 08:00:00');

        $notice = $user->notifications()->first();

        $this->assertSame('Appointment in 1 hour', $notice->data['title']);
        $this->assertStringContainsString('Dr. Cruz at MediByte Clinic', $notice->data['body']);
        $this->assertStringContainsString('on Tue 20 Oct 2026, 9:00 AM', $notice->data['body']);
        $this->assertStringContainsString('Annual skin check', $notice->data['body']);
        $this->assertSame('Dermatology', $notice->data['specialty']);
        $this->assertSame('2026-10-20T09:00:00+08:00', $notice->data['at']);
    }

    public function test_the_morning_of_the_visit_can_be_announced_on_its_own(): void
    {
        $user = $this->track([
            'appointment_lead_minutes' => [],
            'appointment_day_notice' => true,
        ]);

        $this->appoint($user, '2026-10-20 15:00');

        $this->sweep('2026-10-20 07:00:00');
        $this->assertSame([], $this->keys($user));

        $this->sweep('2026-10-20 08:00:00');
        $this->sweep('2026-10-20 09:00:00');

        $this->assertSame(['appointment:1:2026-10-20 15:00:today'], $this->keys($user));
        $this->assertSame('Appointment today', $user->notifications()->first()->data['title']);
    }

    public function test_silencing_one_feature_leaves_the_other_reminding(): void
    {
        $user = $this->track([
            'appointment_reminders_enabled' => false,
            'medication_lead_minutes' => [0],
        ]);

        $this->appoint($user, '2026-10-20 08:00');
        $this->prescribe($user, ['08:00']);

        $this->sweep('2026-10-20 08:00:00');

        $this->assertSame(['medication:1:2026-10-20:08:00:lead0'], $this->keys($user));
    }

    public function test_each_chosen_medication_lead_arrives_before_the_dose(): void
    {
        $user = $this->track(['medication_lead_minutes' => [30, 0]]);

        $this->prescribe($user, ['08:00']);

        $this->sweep('2026-10-20 07:30:00');
        $this->sweep('2026-10-20 07:45:00');

        $this->assertSame(['medication:1:2026-10-20:08:00:lead30'], $this->keys($user));
        $this->assertSame('Metformin (500 mg) is due at 08:00, in 30 minutes.', $this->body($user, 'lead30'));
        $this->assertSame('Medication due soon', $this->notice($user, 'lead30')['title']);

        $this->sweep('2026-10-20 08:00:00');

        $this->assertSame([
            'medication:1:2026-10-20:08:00:lead0',
            'medication:1:2026-10-20:08:00:lead30',
        ], $this->keys($user));
        $this->assertSame('Time for Metformin (500 mg) at 08:00.', $this->body($user, 'lead0'));
        $this->assertSame('Medication reminder', $this->notice($user, 'lead0')['title']);
    }

    public function test_a_dose_still_unanswered_keeps_asking_until_it_is_opened(): void
    {
        $user = $this->track([
            'medication_lead_minutes' => [0],
            'medication_repeat_minutes' => 30,
        ]);

        $this->prescribe($user, ['08:00']);

        $this->sweep('2026-10-20 08:00:00');
        $this->sweep('2026-10-20 08:30:00');
        $this->sweep('2026-10-20 09:00:00');

        $this->assertSame([
            'medication:1:2026-10-20:08:00:lead0',
            'medication:1:2026-10-20:08:00:lead0:every30:1',
            'medication:1:2026-10-20:08:00:lead0:every30:2',
        ], $this->keys($user));

        $user->notifications()
            ->where('data->dedupe_key', 'medication:1:2026-10-20:08:00:lead0')
            ->first()
            ->markAsRead();

        $this->sweep('2026-10-20 09:30:00');

        $this->assertCount(3, $this->keys($user));
    }

    public function test_a_dose_nobody_answered_stops_being_nudged_after_the_grace_period(): void
    {
        $user = $this->track([
            'medication_lead_minutes' => [0],
            'medication_repeat_minutes' => 30,
        ]);

        $this->prescribe($user, ['08:00']);

        $this->sweep('2026-10-20 08:00:00');
        $this->sweep('2026-10-20 11:00:00');

        $this->assertSame(['medication:1:2026-10-20:08:00:lead0'], $this->keys($user));
    }

    public function test_a_reminder_thrown_away_is_not_re_sent_or_followed_up(): void
    {
        $user = $this->track([
            'appointment_lead_minutes' => [1440],
            'appointment_repeat_minutes' => 60,
        ]);

        Sanctum::actingAs($user);

        $this->appoint($user, '2026-10-21 09:00');

        $this->sweep('2026-10-20 09:00:00');
        $this->assertCount(1, $this->keys($user));

        $this->deleteJson('/api/v1/notifications/'.$user->notifications()->first()->id)
            ->assertOk();

        $this->sweep('2026-10-20 10:00:00');
        $this->sweep('2026-10-20 11:00:00');

        $this->assertSame([], $this->keys($user));
    }

    public function test_every_upcoming_visit_is_reminded_for_not_only_the_first(): void
    {
        $user = $this->track(['appointment_lead_minutes' => [10080]]);

        $this->appoint($user, '2026-10-20 09:00');
        $this->appoint($user, '2026-10-21 09:00');

        $this->sweep('2026-10-20 08:00:00');

        $this->assertSame([
            'appointment:1:2026-10-20 09:00:lead10080',
            'appointment:2:2026-10-21 09:00:lead10080',
        ], $this->keys($user));
    }

    public function test_every_dose_of_the_day_is_reminded_for_not_only_the_first(): void
    {
        $user = $this->track(['medication_lead_minutes' => [0]]);

        $this->prescribe($user, ['08:00', '20:00']);

        $this->sweep('2026-10-20 08:00:00');
        $this->sweep('2026-10-20 08:05:00');

        $this->assertSame(['medication:1:2026-10-20:08:00:lead0'], $this->keys($user));

        $this->sweep('2026-10-20 20:00:00');

        $this->assertSame([
            'medication:1:2026-10-20:08:00:lead0',
            'medication:1:2026-10-20:20:00:lead0',
        ], $this->keys($user));
    }

    /**
     * @param  array<string, mixed>  $preferences
     */
    private function track(array $preferences): User
    {
        $user = User::factory()->create();

        $user->forceFill(array_merge([
            'sex' => 'male',
            'reminders_enabled' => true,
        ], $preferences))->save();

        return $user;
    }

    private function appoint(User $user, string $at): Appointment
    {
        return Appointment::create([
            'user_id' => $user->id,
            'doctor_name' => 'Dr. Cruz',
            'appointment_at' => $at,
            'status' => 'scheduled',
        ]);
    }

    /**
     * @param  array<int, string>  $times
     */
    private function prescribe(User $user, array $times): Medication
    {
        return Medication::create([
            'user_id' => $user->id,
            'name' => 'Metformin',
            'dosage' => '500 mg',
            'times' => $times,
            'start_date' => '2026-10-20',
            'is_active' => true,
        ]);
    }

    private function sweep(string $at): void
    {
        Carbon::setTestNow($at);

        $this->artisan('medibyte:send-reminders')->assertExitCode(0);
    }

    /**
     * @return array<int, string>
     */
    private function keys(User $user): array
    {
        $keys = $user->notifications()->get()
            ->map(fn ($notification) => $notification->data['dedupe_key'])
            ->values()
            ->all();

        sort($keys);

        return $keys;
    }

    /**
     * @return array<string, mixed>
     */
    private function notice(User $user, string $keySuffix): array
    {
        foreach ($user->notifications()->get() as $notification) {
            if (str_ends_with($notification->data['dedupe_key'], $keySuffix)) {
                return $notification->data;
            }
        }

        $this->fail('No reminder was sent for a key ending '.$keySuffix.'.');
    }

    private function body(User $user, string $keySuffix): string
    {
        return $this->notice($user, $keySuffix)['body'];
    }
}
