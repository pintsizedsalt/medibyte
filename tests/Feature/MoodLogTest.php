<?php

namespace Tests\Feature;

use App\Models\MoodLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MoodLogTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_user_can_create_valid_mood_entry(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/mood-logs', [
            'mood' => 'calm',
            'intensity' => 4,
            'cried' => false,
            'has_big_event' => true,
            'event_note' => 'Graduation day',
            'impacts' => ['family', 'achievement'],
            'reflection' => 'Felt peaceful and proud of the milestone.',
            'log_date' => now()->toDateString(),
        ]);

        $response->assertCreated()
            ->assertJsonPath('mood', 'calm')
            ->assertJsonPath('intensity', 4)
            ->assertJsonPath('cried', false)
            ->assertJsonPath('has_big_event', true)
            ->assertJsonPath('event_note', 'Graduation day')
            ->assertJsonPath('log_date', now()->toDateString());

        $this->assertDatabaseHas('mood_logs', [
            'user_id' => $user->id,
            'mood' => 'calm',
            'intensity' => 4,
            'event_note' => 'Graduation day',
            'log_date' => now()->toDateString(),
        ]);
    }

    public function test_rejects_invalid_mood_and_intensity(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/mood-logs', [
            'mood' => 'super_ecstatic_invalid',
            'intensity' => 10,
            'log_date' => now()->toDateString(),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['mood', 'intensity']);
    }

    public function test_rejects_missing_or_malformed_date(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $responseMissing = $this->postJson('/api/v1/mood-logs', [
            'mood' => 'happy',
            'intensity' => 3,
        ]);
        $responseMissing->assertStatus(422)
            ->assertJsonValidationErrors(['log_date']);

        $responseMalformed = $this->postJson('/api/v1/mood-logs', [
            'mood' => 'happy',
            'intensity' => 3,
            'log_date' => '26-10-2026',
        ]);
        $responseMalformed->assertStatus(422)
            ->assertJsonValidationErrors(['log_date']);
    }

    public function test_rejects_future_dates_on_creation_and_update(): void
    {
        Carbon::setTestNow('2026-10-26 12:00:00');
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $tomorrow = now()->addDay()->toDateString();

        $createResponse = $this->postJson('/api/v1/mood-logs', [
            'mood' => 'energetic',
            'intensity' => 5,
            'log_date' => $tomorrow,
        ]);
        $createResponse->assertStatus(422)
            ->assertJsonValidationErrors(['log_date']);

        $existing = $user->moodLogs()->create([
            'mood' => 'relaxed',
            'intensity' => 3,
            'log_date' => now()->toDateString(),
        ]);

        $updateResponse = $this->putJson('/api/v1/mood-logs/'.$existing->id, [
            'mood' => 'relaxed',
            'intensity' => 3,
            'log_date' => $tomorrow,
        ]);
        $updateResponse->assertStatus(422)
            ->assertJsonValidationErrors(['log_date']);
    }

    public function test_user_can_edit_past_entry_while_preserving_its_date(): void
    {
        Carbon::setTestNow('2026-10-26 12:00:00');
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $pastDate = '2026-10-10';
        $log = $user->moodLogs()->create([
            'mood' => 'tired',
            'intensity' => 2,
            'log_date' => $pastDate,
        ]);

        $response = $this->putJson('/api/v1/mood-logs/'.$log->id, [
            'mood' => 'content',
            'intensity' => 4,
            'log_date' => $pastDate,
            'reflection' => 'Felt much better after resting.',
        ]);

        $response->assertOk()
            ->assertJsonPath('mood', 'content')
            ->assertJsonPath('intensity', 4)
            ->assertJsonPath('log_date', $pastDate)
            ->assertJsonPath('reflection', 'Felt much better after resting.');

        $this->assertDatabaseHas('mood_logs', [
            'id' => $log->id,
            'mood' => 'content',
            'intensity' => 4,
            'log_date' => $pastDate,
        ]);
    }

    public function test_validates_date_query_parameter_for_filtering(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $valid = $this->getJson('/api/v1/mood-logs?date=2026-10-20');
        $valid->assertOk();

        $invalid = $this->getJson('/api/v1/mood-logs?date=invalid-date');
        $invalid->assertStatus(422)
            ->assertJsonValidationErrors(['date']);
    }

    public function test_optional_checkboxes_and_text_fields_are_handled_safely(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Submitting with omitted optional fields
        $response = $this->postJson('/api/v1/mood-logs', [
            'mood' => 'neutral',
            'intensity' => 3,
            'log_date' => now()->toDateString(),
        ]);

        $response->assertCreated()
            ->assertJsonPath('cried', false)
            ->assertJsonPath('has_big_event', false)
            ->assertJsonPath('event_note', null)
            ->assertJsonPath('reflection', null);

        // Submitting with truthy checkboxes
        $responseWithChecks = $this->postJson('/api/v1/mood-logs', [
            'mood' => 'sad',
            'intensity' => 2,
            'cried' => true,
            'has_big_event' => false,
            'log_date' => now()->toDateString(),
        ]);

        $responseWithChecks->assertCreated()
            ->assertJsonPath('cried', true)
            ->assertJsonPath('has_big_event', false);
    }

    public function test_user_can_delete_own_mood_entry(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $log = $user->moodLogs()->create([
            'mood' => 'hopeful',
            'intensity' => 4,
            'log_date' => now()->toDateString(),
        ]);

        $response = $this->deleteJson('/api/v1/mood-logs/'.$log->id);
        $response->assertOk()->assertJson(['message' => 'Deleted']);

        $this->assertDatabaseMissing('mood_logs', ['id' => $log->id]);
    }

    public function test_users_cannot_access_update_or_delete_other_users_mood_entries(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $ownersLog = $owner->moodLogs()->create([
            'mood' => 'grateful',
            'intensity' => 5,
            'log_date' => now()->toDateString(),
        ]);

        Sanctum::actingAs($otherUser);

        // Other user shouldn't see owner's log in index
        $listResponse = $this->getJson('/api/v1/mood-logs');
        $listResponse->assertOk()->assertJsonCount(0);

        // Other user cannot update owner's log
        $updateResponse = $this->putJson('/api/v1/mood-logs/'.$ownersLog->id, [
            'mood' => 'angry',
            'intensity' => 1,
            'log_date' => now()->toDateString(),
        ]);
        $updateResponse->assertNotFound();

        // Other user cannot delete owner's log
        $deleteResponse = $this->deleteJson('/api/v1/mood-logs/'.$ownersLog->id);
        $deleteResponse->assertNotFound();

        $this->assertDatabaseHas('mood_logs', [
            'id' => $ownersLog->id,
            'mood' => 'grateful',
        ]);
    }

    public function test_mood_log_model_relationships_and_casts(): void
    {
        $user = User::factory()->create();
        $log = $user->moodLogs()->create([
            'mood' => 'loved',
            'intensity' => '5',
            'cried' => 1,
            'has_big_event' => 0,
            'log_date' => '2026-10-25',
        ]);

        $this->assertInstanceOf(User::class, $log->user);
        $this->assertEquals($user->id, $log->user->id);
        $this->assertIsInt($log->intensity);
        $this->assertIsBool($log->cried);
        $this->assertTrue($log->cried);
        $this->assertIsBool($log->has_big_event);
        $this->assertFalse($log->has_big_event);
        $this->assertEquals('2026-10-25', $log->log_date->format('Y-m-d'));
    }
}
