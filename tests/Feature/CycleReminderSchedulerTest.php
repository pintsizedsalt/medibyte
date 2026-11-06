<?php

namespace Tests\Feature;

use App\Models\PeriodLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Cycle reminders are judged the same way as appointment and medication ones:
 * by the notification rows a person ends up holding, anchored to the predicted
 * date so a changed prediction never doubles up a notice.
 */
class CycleReminderSchedulerTest extends TestCase
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

    public function test_a_period_due_notice_arrives_the_chosen_days_before(): void
    {
        $user = $this->tracker(['cycle_lead_days' => 2, 'cycle_reminder_time' => '08:00']);
        $this->periods($user);

        $this->sweep('2026-10-22 07:00:00');
        $this->assertSame([], $this->keys($user));

        $this->sweep('2026-10-22 08:05:00');

        $this->assertSame(['cycle:2026-10-24:due'], $this->keys($user));
        $this->assertSame('Your period is expected in 2 days', $this->first($user)['title']);
    }

    public function test_the_due_notice_is_replaced_by_a_late_one_after_the_day(): void
    {
        $user = $this->tracker(['cycle_lead_days' => 1]);
        $this->periods($user);

        $this->sweep('2026-10-25 08:00:00');

        $this->assertSame(['cycle:2026-10-24:late'], $this->keys($user));
    }

    public function test_a_late_period_gets_a_late_notice_and_a_doctor_nudge(): void
    {
        $user = $this->tracker([]);
        $this->periods($user);

        $this->sweep('2026-10-31 08:00:00');

        $this->assertSame([
            'cycle:2026-10-24:doctor',
            'cycle:2026-10-24:late',
        ], $this->keys($user));

        $this->assertSame('Your period is 7 days late', $this->first($user)['title']);
    }

    public function test_a_week_overdue_adds_the_doctor_nudge_once(): void
    {
        $user = $this->tracker([]);
        $this->periods($user);

        $this->sweep('2026-10-31 08:00:00');
        $this->sweep('2026-11-01 08:00:00');
        $this->sweep('2026-11-02 08:00:00');

        $this->assertCount(2, $this->keys($user));
    }

    public function test_discreet_mode_hides_the_cycle_wording(): void
    {
        $user = $this->tracker(['cycle_discreet' => true]);
        $this->periods($user);

        $this->sweep('2026-10-22 08:00:00');

        $this->assertSame('Health reminder', $this->first($user)['title']);
        $this->assertSame('You have a private health update. Open MediByte to view it.', $this->first($user)['body']);
    }

    public function test_pausing_cycles_stops_cycle_reminders(): void
    {
        $user = $this->tracker(['cycle_paused' => true]);
        $this->periods($user);

        $this->sweep('2026-10-22 08:00:00');

        $this->assertSame([], $this->keys($user));
    }

    public function test_the_birth_control_pill_nudge_arrives_at_its_time_once_a_day(): void
    {
        $user = $this->tracker(['cycle_pill_reminder' => true, 'cycle_pill_time' => '09:00']);

        $this->sweep('2026-10-22 08:59:00');
        $this->assertSame([], $this->keys($user));

        $this->sweep('2026-10-22 09:00:00');
        $this->assertSame(['cycle-pill:2026-10-22'], $this->keys($user));
        $this->assertSame('Birth control pill', $this->first($user)['title']);

        $this->sweep('2026-10-22 09:05:00');
        $this->sweep('2026-10-22 18:00:00');
        $this->assertCount(1, $this->keys($user));
    }

    public function test_the_daily_symptom_log_nudge_waits_for_its_time_and_skips_logged_days(): void
    {
        $user = $this->tracker(['cycle_daily_log_reminder' => true, 'cycle_daily_log_time' => '20:00']);

        $this->sweep('2026-10-22 19:59:00');
        $this->assertSame([], $this->keys($user));

        $this->sweep('2026-10-22 20:01:00');
        $this->assertSame(['cycle-daily:2026-10-22'], $this->keys($user));
        $this->assertSame('Daily symptom log', $this->first($user)['title']);

        $user->symptomLogs()->create(['log_date' => '2026-10-23', 'symptom' => 'Headache', 'severity' => 2]);
        $this->sweep('2026-10-23 20:01:00');
        $this->assertCount(1, $this->keys($user));
    }

    /**
     * @param  array<string, mixed>  $preferences
     */
    private function tracker(array $preferences): User
    {
        $user = User::factory()->create();

        $user->forceFill(array_merge([
            'sex' => 'female',
            'period_status' => 'active',
            'reminders_enabled' => true,
            'cycle_reminders_enabled' => true,
            'cycle_lead_days' => 2,
            'cycle_reminder_time' => '08:00',
            'cycle_late_reminder' => true,
        ], $preferences))->save();

        return $user;
    }

    private function periods(User $user): void
    {
        foreach (['2026-07-04', '2026-08-01', '2026-08-29', '2026-09-26'] as $start) {
            PeriodLog::create(['user_id' => $user->id, 'start_date' => $start]);
        }
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
    private function first(User $user): array
    {
        return $user->notifications()->orderBy('created_at')->first()->data;
    }
}
