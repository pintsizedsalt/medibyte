<?php

namespace Tests\Feature;

use App\Models\DailyLog;
use App\Models\PeriodLog;
use App\Models\SleepLog;
use App\Models\User;
use App\Models\WalkingLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_summarises_health_progress_and_cycle_for_female_users(): void
    {
        $user = User::factory()->create([
            'sex' => 'female',
            'period_status' => 'active',
            'cycle_length' => 28,
            'water_goal_ml' => 2000,
            'sleep_goal_hours' => 8,
            'walking_goal_steps' => 8000,
        ]);

        $date = today()->toDateString();

        DailyLog::create(['user_id' => $user->id, 'log_date' => $date, 'water_ml' => 1000]);
        SleepLog::create(['user_id' => $user->id, 'log_date' => $date, 'hours' => 8]);
        WalkingLog::create(['user_id' => $user->id, 'log_date' => $date, 'activity_type' => 'walking', 'steps' => 4000]);
        PeriodLog::create([
            'user_id' => $user->id,
            'start_date' => today()->subDays(3)->toDateString(),
            'end_date' => today()->toDateString(),
            'flow' => 'medium',
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('health.score', 67)
            ->assertJsonPath('health.status', 'On track')
            ->assertJsonPath('health.components.0.key', 'sleep')
            ->assertJsonPath('health.week.6.score', 67)
            ->assertJsonPath('cycle.cycle_day', 4)
            ->assertJsonPath('cycle.days_until_next', 25)
            ->assertJsonCount(5, 'metrics')
            ->assertJsonPath('metrics.3.key', 'cycle')
            ->assertJsonMissingPath('unread_notifications');
    }

    public function test_dashboard_uses_mood_instead_of_cycle_for_other_users(): void
    {
        $user = User::factory()->create([
            'sex' => 'male',
            'period_status' => null,
            'water_goal_ml' => 2000,
            'sleep_goal_hours' => 8,
            'walking_goal_steps' => 8000,
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('cycle', null)
            ->assertJsonPath('metrics.3.key', 'mood')
            ->assertJsonCount(5, 'metrics');
    }
}
