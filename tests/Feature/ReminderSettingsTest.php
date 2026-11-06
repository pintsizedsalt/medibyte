<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Reminders are configured per feature, so the API is the only place that decides
 * which lead times exist and what an unset choice means.
 */
class ReminderSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    public function test_an_account_that_never_chose_keeps_the_notices_it_already_had(): void
    {
        $this->assertSame(User::DEFAULT_APPOINTMENT_LEADS, $this->user->appointmentLeads());
        $this->assertSame(User::DEFAULT_MEDICATION_LEADS, $this->user->medicationLeads());
        $this->assertTrue($this->user->appointment_reminders_enabled);
        $this->assertTrue($this->user->medication_reminders_enabled);
        $this->assertFalse($this->user->appointment_day_notice);
        $this->assertNull($this->user->appointmentRepeat());
        $this->assertNull($this->user->medicationRepeat());
    }

    public function test_lead_times_are_saved_once_each_and_nearest_first(): void
    {
        $this->postJson('/api/v1/profile', [
            'appointment_lead_minutes' => [10080, 60, 60, 1440],
            'medication_lead_minutes' => [0, 15],
        ])->assertCreated();

        $settled = $this->user->fresh();

        $this->assertSame([60, 1440, 10080], $settled->appointment_lead_minutes);
        $this->assertSame([0, 15], $settled->medication_lead_minutes);
    }

    public function test_a_day_notice_and_a_repeat_interval_are_both_remembered(): void
    {
        $this->postJson('/api/v1/profile', [
            'appointment_day_notice' => true,
            'appointment_repeat_minutes' => 60,
            'medication_repeat_minutes' => 15,
        ])->assertCreated();

        $settled = $this->user->fresh();

        $this->assertTrue($settled->appointment_day_notice);
        $this->assertSame(60, $settled->appointmentRepeat());
        $this->assertSame(15, $settled->medicationRepeat());
    }

    public function test_a_repeat_interval_can_be_given_up_again(): void
    {
        $this->postJson('/api/v1/profile', ['medication_repeat_minutes' => 30])->assertCreated();
        $this->assertSame(30, $this->user->fresh()->medicationRepeat());

        $this->postJson('/api/v1/profile', ['medication_repeat_minutes' => null])->assertCreated();
        $this->assertNull($this->user->fresh()->medicationRepeat());
    }

    /**
     * A lead time is either one of the offered choices or a custom amount, so what
     * refuses a number is the window it sits in: a month ahead of an appointment, a
     * day ahead of a dose, and only the listed rates for repeating.
     */
    public function test_a_lead_time_outside_the_window_of_its_feature_is_refused(): void
    {
        $this->postJson('/api/v1/profile', ['appointment_lead_minutes' => [User::CUSTOM_LEAD_CEILINGS['appointment'] + 1]])
            ->assertStatus(422)
            ->assertJsonValidationErrors('appointment_lead_minutes.0');

        $this->postJson('/api/v1/profile', ['medication_lead_minutes' => [User::CUSTOM_LEAD_CEILINGS['medication'] + 1]])
            ->assertStatus(422)
            ->assertJsonValidationErrors('medication_lead_minutes.0');

        $this->postJson('/api/v1/profile', ['appointment_repeat_minutes' => 7])
            ->assertStatus(422)
            ->assertJsonValidationErrors('appointment_repeat_minutes');

        $this->assertNull($this->user->fresh()->appointment_lead_minutes);
    }

    public function test_a_custom_lead_time_is_kept_alongside_the_offered_ones(): void
    {
        $this->postJson('/api/v1/profile', [
            'appointment_lead_minutes' => [45, 1440, 45],
            'medication_lead_minutes' => [20, 0],
        ])->assertCreated();

        $settled = $this->user->fresh();

        $this->assertSame([45, 1440], $settled->appointment_lead_minutes);
        $this->assertSame([45, 1440], $settled->appointmentLeads());
        $this->assertSame([0, 20], $settled->medicationLeads());

        $profile = $this->getJson('/api/v1/profile')->assertOk()->json();

        $this->assertSame([45, 1440], $profile['reminder_preferences']['appointment']['leads']);
    }

    public function test_either_feature_can_be_silenced_on_its_own(): void
    {
        $this->postJson('/api/v1/profile', [
            'appointment_reminders_enabled' => false,
            'medication_reminders_enabled' => true,
        ])->assertCreated();

        $preferences = $this->user->fresh()->reminder_preferences;

        $this->assertFalse($preferences['appointment']['enabled']);
        $this->assertTrue($preferences['medication']['enabled']);
    }

    public function test_unticking_every_notice_means_nothing_is_sent(): void
    {
        $this->postJson('/api/v1/profile', [
            'appointment_lead_minutes' => [],
            'medication_lead_minutes' => [],
        ])->assertCreated();

        $settled = $this->user->fresh();

        $this->assertSame([], $settled->appointmentLeads());
        $this->assertSame([], $settled->medicationLeads());
    }

    public function test_the_profile_api_answers_with_the_settled_preferences_the_form_paints_from(): void
    {
        $this->postJson('/api/v1/profile', ['medication_lead_minutes' => [15, 0]])->assertCreated();

        $profile = $this->getJson('/api/v1/profile')->assertOk()->json();

        $this->assertSame([0, 15], $profile['reminder_preferences']['medication']['leads']);
        $this->assertSame(User::DEFAULT_APPOINTMENT_LEADS, $profile['reminder_preferences']['appointment']['leads']);
        $this->assertTrue($profile['reminder_preferences']['appointment']['enabled']);
    }

    public function test_the_settings_page_offers_every_choice_the_server_accepts(): void
    {
        $page = $this->get('/settings')->assertOk()->getContent();

        $options = [User::APPOINTMENT_LEADS, User::MEDICATION_LEADS, User::REPEAT_INTERVALS];

        foreach ($options as $labels) {
            foreach ($labels as $label) {
                $this->assertStringContainsString($label, $page);
            }
        }

        $this->assertStringContainsString('id="appointment_reminders_enabled"', $page);
        $this->assertStringContainsString('id="medication_reminders_enabled"', $page);
    }

    public function test_the_cycle_card_appears_only_for_accounts_with_the_tracker_on(): void
    {
        $withoutTracker = $this->get('/settings')->assertOk()->getContent();

        $this->assertStringNotContainsString('id="cycle_reminders_enabled"', $withoutTracker);

        $this->user->forceFill(['sex' => 'female', 'period_status' => 'active'])->save();

        $withTracker = $this->get('/settings')->assertOk()->getContent();

        $this->assertStringContainsString('data-reminder-card="cycle"', $withTracker);
        $this->assertStringContainsString('id="cycle_reminders_enabled"', $withTracker);
        $this->assertStringContainsString('id="cycle_lead_days"', $withTracker);
        $this->assertStringContainsString('id="cycle_fertile_reminder"', $withTracker);
        $this->assertStringContainsString('id="cycle_daily_log_reminder"', $withTracker);
        $this->assertStringContainsString('id="cycle_discreet"', $withTracker);

        foreach (User::CYCLE_LEAD_DAYS as $label) {
            $this->assertStringContainsString($label, $withTracker);
        }
    }
}
