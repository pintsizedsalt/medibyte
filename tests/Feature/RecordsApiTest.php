<?php

namespace Tests\Feature;

use App\Models\DailyLog;
use App\Models\MealLog;
use App\Models\MoodLog;
use App\Models\PeriodLog;
use App\Models\SleepLog;
use App\Models\User;
use App\Models\WalkingLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RecordsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_returns_counts_latest_values_and_recent_activity(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['sex' => 'female', 'period_status' => 'not_yet'])->save();
        Sanctum::actingAs($user);

        $today = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();

        DailyLog::create(['user_id' => $user->id, 'log_date' => $today, 'water_ml' => 1250]);
        DailyLog::create(['user_id' => $user->id, 'log_date' => $yesterday, 'water_ml' => 500]);
        SleepLog::create(['user_id' => $user->id, 'log_date' => $today, 'hours' => 8]);
        WalkingLog::create(['user_id' => $user->id, 'log_date' => $today, 'activity_type' => 'walking', 'steps' => 4000]);
        MealLog::create(['user_id' => $user->id, 'log_date' => $today, 'meal_type' => 'lunch', 'food_name' => 'Rice bowl', 'calories' => 500]);
        MoodLog::create(['user_id' => $user->id, 'log_date' => $today, 'mood' => 'happy', 'intensity' => 4]);

        $response = $this->getJson('/api/v1/records/summary')->assertOk();

        $response->assertJsonPath('total_records', 6);

        $response->assertJsonPath('features.0.key', 'water');
        $response->assertJsonPath('features.0.count', 2);
        $response->assertJsonPath('features.0.last_date', $today);
        $response->assertJsonPath('features.0.last_value', '1.3 L');

        $response->assertJsonPath('features.3.key', 'meals');
        $response->assertJsonPath('features.3.last_value', 'Rice bowl');

        $recentKeys = collect($response->json('recent'))->pluck('key');
        $this->assertContains('water', $recentKeys);
        $this->assertContains('mood', $recentKeys);
    }

    public function test_summary_includes_cycle_only_when_period_tracking_is_enabled(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['sex' => 'female', 'period_status' => 'not_yet'])->save();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/records/summary')
            ->assertOk()
            ->assertJsonMissingPath('features.11');

        $user->forceFill(['period_status' => 'active'])->save();

        $keys = collect($this->getJson('/api/v1/records/summary')->assertOk()->json('features'))->pluck('key');
        $this->assertContains('period', $keys);
    }

    public function test_day_returns_cross_feature_records_for_a_single_date(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['sex' => 'female', 'period_status' => 'active'])->save();
        Sanctum::actingAs($user);

        $date = now()->subDays(2)->toDateString();
        $otherDate = now()->subDays(5)->toDateString();

        DailyLog::create(['user_id' => $user->id, 'log_date' => $date, 'water_ml' => 1000]);
        DailyLog::create(['user_id' => $user->id, 'log_date' => $otherDate, 'water_ml' => 250]);
        SleepLog::create(['user_id' => $user->id, 'log_date' => $date, 'hours' => 7.5]);
        WalkingLog::create(['user_id' => $user->id, 'log_date' => $date, 'activity_type' => 'walking', 'steps' => 6000, 'minutes' => 45]);
        MealLog::create(['user_id' => $user->id, 'log_date' => $date, 'meal_type' => 'breakfast', 'food_name' => 'Oatmeal', 'calories' => 300]);
        MoodLog::create(['user_id' => $user->id, 'log_date' => $date, 'mood' => 'calm', 'intensity' => 3]);
        PeriodLog::create(['user_id' => $user->id, 'start_date' => $date, 'end_date' => $date, 'flow' => 'light']);

        $response = $this->getJson('/api/v1/records/day?date='.$date)->assertOk();

        $response->assertJsonPath('date', $date);
        $response->assertJsonPath('water.total_ml', 1000);
        $response->assertJsonPath('sleep.0.hours', 7.5);
        $response->assertJsonPath('walking.0.label', '6,000 steps · 45 min');
        $response->assertJsonPath('meals.0.label', 'Oatmeal · 300 kcal');
        $response->assertJsonPath('moods.0.label', 'Calm · 3/5');
        $response->assertJsonPath('period.0.label', 'Period day · Light');
    }

    public function test_day_requires_a_valid_date(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/records/day')->assertStatus(422)->assertJsonValidationErrors(['date']);

        $this->getJson('/api/v1/records/day?date=not-a-date')->assertStatus(422)->assertJsonValidationErrors(['date']);
    }
}
