<?php

namespace Tests\Feature;

use App\Models\PeriodLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CycleTrackingTest extends TestCase
{
    use RefreshDatabase;

    private function tracker(): User
    {
        $user = User::factory()->create();

        $user->forceFill([
            'sex' => 'female',
            'period_status' => 'active',
            'cycle_length' => 28,
        ])->save();

        Sanctum::actingAs($user);

        return $user;
    }

    public function test_the_overview_returns_prediction_logs_and_days(): void
    {
        $user = $this->tracker();

        PeriodLog::create(['user_id' => $user->id, 'start_date' => '2026-08-29']);
        PeriodLog::create(['user_id' => $user->id, 'start_date' => '2026-09-26']);

        $user->periodDays()->create([
            'log_date' => '2026-10-01',
            'flow' => 'light',
            'pain' => 2,
            'mood' => 'calm',
        ]);

        $response = $this->getJson('/api/v1/cycle?month=2026-10')->assertOk()->json();

        $this->assertArrayHasKey('prediction', $response);
        $this->assertSame('still_learning', $response['prediction']['state']);
        $this->assertCount(2, $response['period_logs']);
        $this->assertCount(1, $response['period_days']);
        $this->assertSame('light', $response['period_days'][0]['flow']);
        $this->assertSame(2, $response['period_days'][0]['pain']);
    }

    public function test_a_day_is_stored_once_and_updated_in_place(): void
    {
        $user = $this->tracker();

        $this->postJson('/api/v1/period-days', [
            'log_date' => '2026-10-01',
            'flow' => 'light',
            'pain' => 2,
        ])->assertCreated();

        $this->postJson('/api/v1/period-days', [
            'log_date' => '2026-10-01',
            'flow' => 'heavy',
        ])->assertCreated();

        $this->assertDatabaseCount('period_days', 1);
        $this->assertSame('heavy', $user->periodDays()->first()->flow);
        $this->assertSame(2, $user->periodDays()->first()->pain);
    }

    public function test_a_daily_log_refuses_unknown_choices(): void
    {
        $this->tracker();

        $this->postJson('/api/v1/period-days', [
            'log_date' => '2026-10-01',
            'flow' => 'torrential',
        ])->assertStatus(422)->assertJsonValidationErrors('flow');

        $this->postJson('/api/v1/period-days', [
            'log_date' => '2026-10-01',
            'pain' => 11,
        ])->assertStatus(422)->assertJsonValidationErrors('pain');
    }

    public function test_cycle_settings_are_saved_and_validated(): void
    {
        $user = $this->tracker();

        $this->postJson('/api/v1/profile', [
            'cycle_reminders_enabled' => true,
            'cycle_lead_days' => 7,
            'cycle_reminder_time' => '07:30',
            'cycle_fertile_reminder' => true,
            'cycle_discreet' => true,
        ])->assertCreated();

        $settled = $user->fresh();

        $this->assertSame(7, $settled->cycleLeadDays());
        $this->assertSame('07:30', $settled->cycleReminderTime());
        $this->assertTrue($settled->cycle_fertile_reminder);
        $this->assertTrue($settled->cycle_discreet);

        $this->postJson('/api/v1/profile', ['cycle_lead_days' => 5])
            ->assertStatus(422)
            ->assertJsonValidationErrors('cycle_lead_days');
    }

    public function test_pausing_cycles_reports_a_paused_state(): void
    {
        $user = $this->tracker();

        PeriodLog::create(['user_id' => $user->id, 'start_date' => '2026-09-26']);

        $this->postJson('/api/v1/profile', [
            'cycle_paused' => true,
            'cycle_pause_reason' => 'pregnancy',
        ])->assertCreated();

        $overview = $this->getJson('/api/v1/cycle')->assertOk()->json();

        $this->assertSame('paused', $overview['prediction']['state']);
        $this->assertSame('pregnancy', $overview['prediction']['pause_reason']);
    }

    public function test_unpausing_clears_the_reason(): void
    {
        $user = $this->tracker();

        $this->postJson('/api/v1/profile', [
            'cycle_paused' => true,
            'cycle_pause_reason' => 'postpartum',
        ])->assertCreated();

        $this->postJson('/api/v1/profile', ['cycle_paused' => false])->assertCreated();

        $this->assertFalse($user->fresh()->cycle_paused);
        $this->assertNull($user->fresh()->cycle_pause_reason);
    }

    public function test_the_tracker_is_guarded_for_profiles_without_a_cycle(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['sex' => 'male', 'period_status' => null])->save();

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/cycle')->assertForbidden();
        $this->postJson('/api/v1/period-days', ['log_date' => '2026-10-01'])->assertForbidden();
    }

    public function test_the_cycle_page_renders_the_dashboard_for_a_tracker(): void
    {
        $this->tracker();

        $this->get('/period')
            ->assertOk()
            ->assertSee('id="cycleDash"', false)
            ->assertSee('id="cycCal"', false)
            ->assertSee('id="cycRing"', false)
            ->assertSee('id="cycDayModal"', false)
            ->assertSee('id="cycPeriodModal"', false)
            ->assertSee('For you today');
    }

    public function test_the_cycle_page_is_guarded_for_profiles_without_a_cycle(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['sex' => 'male', 'period_status' => null])->save();

        Sanctum::actingAs($user);

        $this->get('/period')->assertForbidden();
    }
}
