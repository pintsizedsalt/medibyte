<?php

namespace Tests\Feature;

use App\Models\CustomMood;
use App\Models\MoodLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomMoodTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_user_can_create_custom_mood(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/custom-moods', [
            'name' => 'Euphoric',
        ]);

        $response->assertCreated()
            ->assertJsonPath('name', 'Euphoric')
            ->assertJsonPath('user_id', $user->id);

        $this->assertDatabaseHas('custom_moods', [
            'user_id' => $user->id,
            'name' => 'Euphoric',
        ]);
    }

    public function test_user_can_list_saved_custom_moods(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $user->customMoods()->create(['name' => 'Blissful']);
        $user->customMoods()->create(['name' => 'Serene']);
        $otherUser->customMoods()->create(['name' => 'Secret Mood']);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/custom-moods');

        $response->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.name', 'Blissful')
            ->assertJsonPath('1.name', 'Serene');

        $this->assertFalse(
            collect($response->json())->pluck('name')->contains('Secret Mood')
        );
    }

    public function test_user_can_update_saved_custom_mood(): void
    {
        $user = User::factory()->create();
        $mood = $user->customMoods()->create(['name' => 'Melancholy']);

        Sanctum::actingAs($user);

        $response = $this->putJson('/api/v1/custom-moods/'.$mood->id, [
            'name' => 'Deeply Pensive',
        ]);

        $response->assertOk()
            ->assertJsonPath('name', 'Deeply Pensive');

        $this->assertDatabaseHas('custom_moods', [
            'id' => $mood->id,
            'name' => 'Deeply Pensive',
        ]);
    }

    public function test_user_can_delete_saved_custom_mood(): void
    {
        $user = User::factory()->create();
        $mood = $user->customMoods()->create(['name' => 'Temporary Mood']);

        Sanctum::actingAs($user);

        $response = $this->deleteJson('/api/v1/custom-moods/'.$mood->id);

        $response->assertOk()
            ->assertJson(['message' => 'Custom mood deleted.']);

        $this->assertDatabaseMissing('custom_moods', [
            'id' => $mood->id,
        ]);
    }

    public function test_duplicate_custom_mood_validation_is_case_insensitive(): void
    {
        $user = User::factory()->create();
        $user->customMoods()->create(['name' => 'Tranquil']);

        Sanctum::actingAs($user);

        // Exact match
        $dupResponse = $this->postJson('/api/v1/custom-moods', [
            'name' => 'Tranquil',
        ]);
        $dupResponse->assertStatus(422)
            ->assertJsonValidationErrors(['name']);

        // Case-insensitive match
        $lowerResponse = $this->postJson('/api/v1/custom-moods', [
            'name' => 'tranquil',
        ]);
        $lowerResponse->assertStatus(422)
            ->assertJsonValidationErrors(['name']);

        // Uppercase match
        $upperResponse = $this->postJson('/api/v1/custom-moods', [
            'name' => 'TRANQUIL',
        ]);
        $upperResponse->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_cannot_create_custom_mood_matching_predefined_moods(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Predefined lowercase
        $response1 = $this->postJson('/api/v1/custom-moods', [
            'name' => 'happy',
        ]);
        $response1->assertStatus(422)
            ->assertJsonValidationErrors(['name']);

        // Predefined title case
        $response2 = $this->postJson('/api/v1/custom-moods', [
            'name' => 'Sleepy',
        ]);
        $response2->assertStatus(422)
            ->assertJsonValidationErrors(['name']);

        // Predefined upper case
        $response3 = $this->postJson('/api/v1/custom-moods', [
            'name' => 'CALM',
        ]);
        $response3->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_user_isolation_and_authorization(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $ownersMood = $owner->customMoods()->create(['name' => 'Owner Only']);

        Sanctum::actingAs($otherUser);

        // Other user cannot update owner's mood
        $updateResponse = $this->putJson('/api/v1/custom-moods/'.$ownersMood->id, [
            'name' => 'Hacked Mood',
        ]);
        $updateResponse->assertNotFound();

        // Other user cannot delete owner's mood
        $deleteResponse = $this->deleteJson('/api/v1/custom-moods/'.$ownersMood->id);
        $deleteResponse->assertNotFound();

        // Other user CAN create a mood with the same name without collision
        $createResponse = $this->postJson('/api/v1/custom-moods', [
            'name' => 'Owner Only',
        ]);
        $createResponse->assertCreated()
            ->assertJsonPath('name', 'Owner Only')
            ->assertJsonPath('user_id', $otherUser->id);

        $this->assertDatabaseHas('custom_moods', [
            'id' => $ownersMood->id,
            'user_id' => $owner->id,
            'name' => 'Owner Only',
        ]);
    }

    public function test_user_can_save_mood_checkin_with_custom_mood(): void
    {
        $user = User::factory()->create();
        $user->customMoods()->create(['name' => 'Ecstatic']);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/mood-logs', [
            'mood' => 'Ecstatic',
            'intensity' => 5,
            'log_date' => now()->toDateString(),
            'reflection' => 'Had an amazing breakthrough today!',
        ]);

        $response->assertCreated()
            ->assertJsonPath('mood', 'Ecstatic')
            ->assertJsonPath('intensity', 5);

        $this->assertDatabaseHas('mood_logs', [
            'user_id' => $user->id,
            'mood' => 'Ecstatic',
            'intensity' => 5,
        ]);
    }

    public function test_renaming_or_deleting_custom_mood_does_not_modify_historical_mood_entries(): void
    {
        $user = User::factory()->create();
        $customMood = $user->customMoods()->create(['name' => 'Bittersweet']);

        $log = $user->moodLogs()->create([
            'mood' => 'Bittersweet',
            'intensity' => 3,
            'log_date' => now()->toDateString(),
        ]);

        Sanctum::actingAs($user);

        // Rename custom mood
        $renameResponse = $this->putJson('/api/v1/custom-moods/'.$customMood->id, [
            'name' => 'Nostalgic Aches',
        ]);
        $renameResponse->assertOk();

        // Historical log still has 'Bittersweet'
        $this->assertDatabaseHas('mood_logs', [
            'id' => $log->id,
            'mood' => 'Bittersweet',
        ]);

        // Delete custom mood
        $deleteResponse = $this->deleteJson('/api/v1/custom-moods/'.$customMood->id);
        $deleteResponse->assertOk();

        // Historical log STILL has 'Bittersweet'
        $this->assertDatabaseHas('mood_logs', [
            'id' => $log->id,
            'mood' => 'Bittersweet',
        ]);
    }

    public function test_editing_historical_entry_works_after_custom_mood_deleted(): void
    {
        $user = User::factory()->create();
        $customMood = $user->customMoods()->create(['name' => 'Wistful']);

        $log = $user->moodLogs()->create([
            'mood' => 'Wistful',
            'intensity' => 2,
            'log_date' => '2026-10-15',
            'reflection' => 'Old note',
        ]);

        // Now delete the custom mood from library
        $customMood->delete();
        $this->assertDatabaseMissing('custom_moods', ['name' => 'Wistful']);

        Sanctum::actingAs($user);

        // Updating the historical entry keeping its existing mood must succeed
        $updateResponse = $this->putJson('/api/v1/mood-logs/'.$log->id, [
            'mood' => 'Wistful',
            'intensity' => 4,
            'log_date' => '2026-10-15',
            'reflection' => 'Updated note while keeping historical mood',
        ]);

        $updateResponse->assertOk()
            ->assertJsonPath('mood', 'Wistful')
            ->assertJsonPath('intensity', 4)
            ->assertJsonPath('reflection', 'Updated note while keeping historical mood');

        $this->assertDatabaseHas('mood_logs', [
            'id' => $log->id,
            'mood' => 'Wistful',
            'intensity' => 4,
        ]);
    }

    public function test_moods_endpoint_returns_predefined_and_user_custom_moods(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $user->customMoods()->create(['name' => 'Custom One']);
        $otherUser->customMoods()->create(['name' => 'Other User Mood']);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/moods');

        $response->assertOk();
        $moods = $response->json();

        // Contains predefined moods
        $this->assertTrue(in_array('happy', $moods, true));
        $this->assertTrue(in_array('sleepy', $moods, true));

        // Contains user's custom mood
        $this->assertTrue(in_array('Custom One', $moods, true));

        // Does NOT contain other user's custom mood
        $this->assertFalse(in_array('Other User Mood', $moods, true));
    }

    public function test_deleting_custom_mood_preserves_historical_mood_log_and_history_api_access(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        Sanctum::actingAs($user);

        // 1. Create a custom mood
        $createMoodRes = $this->postJson('/api/v1/custom-moods', [
            'name' => 'Euphoric',
        ]);
        $createMoodRes->assertCreated();
        $customMoodId = $createMoodRes->json('id');

        // 2. Create a historical mood log using that custom mood
        $createLogRes = $this->postJson('/api/v1/mood-logs', [
            'mood' => 'Euphoric',
            'intensity' => 5,
            'log_date' => now()->toDateString(),
            'reflection' => 'Feeling on top of the world',
        ]);
        $createLogRes->assertCreated();
        $logId = $createLogRes->json('id');

        // 3. Delete the custom mood from user\'s custom moods library
        $deleteMoodRes = $this->deleteJson('/api/v1/custom-moods/'.$customMoodId);
        $deleteMoodRes->assertOk();

        // Proves: Deleting a saved custom mood deletes ONLY custom_moods entry
        $this->assertDatabaseMissing('custom_moods', [
            'id' => $customMoodId,
        ]);

        // Proves: Deleting a saved custom mood does not delete its historical mood log
        $this->assertDatabaseHas('mood_logs', [
            'id' => $logId,
            'mood' => 'Euphoric',
            'intensity' => 5,
        ]);

        // Proves: The historical entry remains available through the History API
        $historyRes = $this->getJson('/api/v1/mood-logs');
        $historyRes->assertOk()
            ->assertJsonFragment([
                'id' => $logId,
                'mood' => 'Euphoric',
                'intensity' => 5,
            ]);

        // Proves: The historical entry can still be retrieved and filtered by date
        $filteredRes = $this->getJson('/api/v1/mood-logs?date='.now()->toDateString());
        $filteredRes->assertOk()
            ->assertJsonFragment([
                'id' => $logId,
                'mood' => 'Euphoric',
            ]);

        // Proves: The historical entry can still be edited while retaining its mood
        $editRes = $this->putJson('/api/v1/mood-logs/'.$logId, [
            'mood' => 'Euphoric',
            'intensity' => 4,
            'log_date' => now()->toDateString(),
            'reflection' => 'Still joyful even after mood library deletion',
        ]);
        $editRes->assertOk()
            ->assertJsonPath('mood', 'Euphoric')
            ->assertJsonPath('intensity', 4)
            ->assertJsonPath('reflection', 'Still joyful even after mood library deletion');

        // Proves: Other users cannot access another user\'s custom moods or mood logs
        Sanctum::actingAs($otherUser);

        $otherHistoryRes = $this->getJson('/api/v1/mood-logs');
        $otherHistoryRes->assertOk()
            ->assertJsonMissing(['id' => $logId]);

        $otherEditRes = $this->putJson('/api/v1/mood-logs/'.$logId, [
            'mood' => 'happy',
            'intensity' => 3,
            'log_date' => now()->toDateString(),
        ]);
        $otherEditRes->assertNotFound();

        $otherDeleteRes = $this->deleteJson('/api/v1/mood-logs/'.$logId);
        $otherDeleteRes->assertNotFound();
    }
}

