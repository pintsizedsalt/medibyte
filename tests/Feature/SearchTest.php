<?php

namespace Tests\Feature;

use App\Models\MealLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_finds_features_by_keyword(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/search?q=menstrual')
            ->assertOk()
            ->assertJsonPath('features.0.href', '/period')
            ->assertJsonPath('features.0.label', 'Menstrual cycle');
    }

    public function test_search_finds_previously_logged_meals(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        MealLog::create([
            'user_id' => $user->id,
            'meal_type' => 'lunch',
            'meal_time' => '12:30',
            'food_name' => 'Chicken adobo',
            'calories' => 450,
            'log_date' => now()->toDateString(),
        ]);

        MealLog::create([
            'user_id' => $other->id,
            'meal_type' => 'dinner',
            'food_name' => 'Chicken curry',
            'log_date' => now()->toDateString(),
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/search?q=chicken')
            ->assertOk()
            ->assertJsonCount(1, 'meals')
            ->assertJsonPath('meals.0.title', 'Chicken adobo')
            ->assertJsonPath('meals.0.href', '/meals?date='.now()->toDateString());
    }

    public function test_search_without_a_query_returns_featured_sections(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/search')
            ->assertOk()
            ->assertJsonCount(6, 'features')
            ->assertJsonPath('meals', [])
            ->assertJsonPath('documents', []);
    }
}
