<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\DailyLog;
use App\Models\MealLog;
use App\Models\Medication;
use App\Models\PeriodLog;
use App\Models\SleepLog;
use App\Models\User;
use App\Models\WalkingLog;
use App\Models\WeightLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HealthTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_weight_logs_compute_bmi_without_storing_it(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/weight-logs', [
            'weight_kg' => 70,
            'height_cm' => 175,
            'log_date' => now()->toDateString(),
        ]);

        $response->assertCreated()->assertJsonPath('bmi', 22.86);

        $this->assertFalse(Schema::hasColumn('weight_logs', 'bmi'));
        $this->assertDatabaseHas('weight_logs', [
            'user_id' => $user->id,
            'weight_kg' => 70,
            'height_cm' => 175,
        ]);
    }

    public function test_weight_logs_are_isolated_between_users(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        Sanctum::actingAs($owner);
        $weight = $this->postJson('/api/v1/weight-logs', [
            'weight_kg' => 80,
            'height_cm' => 180,
            'log_date' => now()->toDateString(),
        ])->assertCreated()->json();

        Sanctum::actingAs($other);
        $this->getJson('/api/v1/weight-logs')->assertOk()->assertJsonCount(0);
        $this->putJson('/api/v1/weight-logs/'.$weight['id'], [
            'weight_kg' => 75,
            'height_cm' => 180,
            'log_date' => now()->toDateString(),
        ])->assertNotFound();
    }

    public function test_progress_returns_goal_percentages_and_bmi(): void
    {
        $user = User::factory()->create();
        $user->forceFill([
            'sex' => 'female',
            'period_status' => 'not_yet',
            'water_goal_ml' => 2000,
            'sleep_goal_hours' => 8,
            'walking_goal_steps' => 8000,
            'calorie_goal' => 2000,
            'weight_goal' => 65,
        ])->save();

        $date = now()->toDateString();

        DailyLog::create(['user_id' => $user->id, 'log_date' => $date, 'water_ml' => 1000]);
        SleepLog::create(['user_id' => $user->id, 'log_date' => $date, 'hours' => 8]);
        WalkingLog::create(['user_id' => $user->id, 'log_date' => $date, 'activity_type' => 'walking', 'steps' => 4000]);
        MealLog::create(['user_id' => $user->id, 'log_date' => $date, 'meal_type' => 'lunch', 'food_name' => 'Rice bowl', 'calories' => 500]);
        WeightLog::create(['user_id' => $user->id, 'log_date' => $date, 'weight_kg' => 70, 'height_cm' => 175]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/progress')
            ->assertOk()
            ->assertJsonPath('water_ml.percent', 50)
            ->assertJsonPath('sleep_hours.percent', 100)
            ->assertJsonPath('walking_steps.percent', 50)
            ->assertJsonPath('calories.percent', 25)
            ->assertJsonPath('weight.bmi', 22.86);
    }

    public function test_walking_logs_accept_steps_or_minutes_but_not_neither(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['sex' => 'male'])->save();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/walking-logs', [
            'activity_type' => 'walking',
            'minutes' => 30,
            'log_date' => now()->toDateString(),
        ])->assertCreated();

        $this->postJson('/api/v1/walking-logs', [
            'activity_type' => 'running',
            'steps' => 5000,
            'log_date' => now()->toDateString(),
        ])->assertCreated();

        $this->postJson('/api/v1/walking-logs', [
            'activity_type' => 'walking',
            'log_date' => now()->toDateString(),
        ])->assertStatus(422)->assertJsonValidationErrors(['steps', 'minutes']);
    }

    public function test_sleep_can_be_logged_with_total_hours_or_bedtime(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['sex' => 'male'])->save();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/sleep-logs', [
            'hours' => 7.5,
            'log_date' => now()->toDateString(),
        ])->assertCreated();

        $response = $this->postJson('/api/v1/sleep-logs', [
            'bedtime' => '23:00',
            'wake_time' => '07:00',
            'log_date' => now()->toDateString(),
        ])->assertCreated();

        $this->assertEquals(8.0, (float) $response->json('hours'));
    }

    public function test_cycle_prediction_uses_the_average_gap_between_periods(): void
    {
        Carbon::setTestNow('2026-10-20 10:00:00');

        $user = User::factory()->create();
        $user->forceFill([
            'sex' => 'female',
            'period_status' => 'active',
            'cycle_length' => 30,
        ])->save();

        foreach (['2026-08-25', '2026-09-22', '2026-10-20'] as $start) {
            PeriodLog::create(['user_id' => $user->id, 'start_date' => $start]);
        }

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/period-predict')
            ->assertOk()
            ->assertJsonPath('last_period_start', '2026-10-20')
            ->assertJsonPath('average_cycle_length', 28)
            ->assertJsonPath('based_on_cycles', 2)
            ->assertJsonPath('cycle_day', 1)
            ->assertJsonPath('predicted_next_start', '2026-11-17')
            ->assertJsonPath('days_until_next', 28);
    }

    public function test_reminders_can_be_turned_off_and_on(): void
    {
        $user = User::factory()->create();
        $user->forceFill([
            'sex' => 'male',
            'reminders_enabled' => false,
        ])->save();
        $profile = $user;

        Medication::create([
            'user_id' => $user->id,
            'name' => 'Paracetamol',
            'dosage' => '500 mg',
            'times' => ['08:00', '20:00'],
            'start_date' => now()->toDateString(),
            'is_active' => true,
            'food_instruction' => 'after_meal',
        ]);

        Appointment::create([
            'user_id' => $user->id,
            'doctor_name' => 'Dr. Cruz',
            'appointment_at' => now()->addDays(2),
            'status' => 'scheduled',
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/reminders')
            ->assertOk()
            ->assertJsonPath('enabled', false)
            ->assertJsonCount(0, 'medications')
            ->assertJsonCount(0, 'appointments');

        $profile->update(['reminders_enabled' => true]);

        $this->getJson('/api/v1/reminders')
            ->assertOk()
            ->assertJsonPath('enabled', true)
            ->assertJsonCount(2, 'medications')
            ->assertJsonCount(1, 'appointments');
    }
}
