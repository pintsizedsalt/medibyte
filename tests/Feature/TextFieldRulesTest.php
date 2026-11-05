<?php

namespace Tests\Feature;

use App\Models\MoodLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Every form field is held to the characters its data can actually contain, and
 * the names people type get tidied into title case on the way in.
 */
class TextFieldRulesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Validator::fakeDnsLookups();
    }

    private function user(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_a_doctor_name_rejects_digits_and_symbols(): void
    {
        $this->user();

        $this->postJson('/api/v1/appointments', [
            'doctor_name' => 'Dr 123 $$$',
            'appointment_at' => now()->addDay()->toDateTimeString(),
        ])->assertStatus(422)->assertJsonValidationErrors('doctor_name');

        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_a_doctor_name_keeps_the_punctuation_real_names_use(): void
    {
        $user = $this->user();

        foreach (["O'brien marcus cruz", 'dr. junrey perez', 'ada smith-jones'] as $typed) {
            $response = $this->postJson('/api/v1/appointments', [
                'doctor_name' => $typed,
                'appointment_at' => now()->addDay()->toDateTimeString(),
            ])->assertCreated();

            $this->assertNotSame($typed, $response->json('doctor_name'));
        }

        $this->assertSame([
            "O'brien Marcus Cruz",
            'Dr. Junrey Perez',
            'Ada Smith-Jones',
        ], $user->appointments()->orderBy('id')->pluck('doctor_name')->all());
    }

    public function test_appointment_contact_and_address_accept_dialable_and_street_characters(): void
    {
        $this->user();

        $this->postJson('/api/v1/appointments', [
            'doctor_name' => 'Rhea Vance',
            'hospital' => 'st. luke\'s medical center',
            'contact_number' => '+63 917-123-4567',
            'office_address' => 'Unit 5, #2 Makati Ave. (Medical City)',
            'appointment_at' => now()->addDay()->toDateTimeString(),
        ])->assertCreated()
            ->assertJsonPath('hospital', 'St. Luke\'s Medical Center')
            ->assertJsonPath('contact_number', '+63 917-123-4567')
            ->assertJsonPath('office_address', 'Unit 5, #2 Makati Ave. (Medical City)');
    }

    public function test_appointment_contact_rejects_letters(): void
    {
        $this->user();

        $this->postJson('/api/v1/appointments', [
            'doctor_name' => 'Rhea Vance',
            'contact_number' => 'call me maybe',
            'appointment_at' => now()->addDay()->toDateTimeString(),
        ])->assertStatus(422)->assertJsonValidationErrors('contact_number');
    }

    public function test_medications_allow_numbers_in_the_name_and_keep_dosage_lowercase(): void
    {
        $user = $this->user();

        $this->postJson('/api/v1/medications', [
            'name' => 'vitamin b12',
            'dosage' => '500 mg',
            'start_date' => now()->toDateString(),
        ])->assertCreated()
            ->assertJsonPath('name', 'Vitamin B12')
            ->assertJsonPath('dosage', '500 mg');

        $this->postJson('/api/v1/medications', [
            'name' => 'metformin <b>500</b>',
            'start_date' => now()->toDateString(),
        ])->assertStatus(422)->assertJsonValidationErrors('name');

        $this->assertSame(1, $user->medications()->count());
    }

    public function test_symptom_and_illness_names_reject_symbols(): void
    {
        $this->user();

        $this->postJson('/api/v1/symptom-logs', [
            'symptom' => 'headache ***',
            'severity' => 3,
            'log_date' => now()->toDateString(),
        ])->assertStatus(422)->assertJsonValidationErrors('symptom');

        $this->postJson('/api/v1/symptom-logs', [
            'symptom' => 'dizzy',
            'illness_name' => 'cold @#$',
            'severity' => 2,
            'log_date' => now()->toDateString(),
        ])->assertStatus(422)->assertJsonValidationErrors('illness_name');

        $this->assertDatabaseCount('symptom_logs', 0);
    }

    public function test_a_custom_mood_rejects_digits_and_is_stored_title_cased(): void
    {
        $user = $this->user();

        $this->postJson('/api/v1/custom-moods', ['name' => 'meh 420'])
            ->assertStatus(422)->assertJsonValidationErrors('name');

        $this->postJson('/api/v1/custom-moods', ['name' => 'kind    of   tired'])
            ->assertCreated()
            ->assertJsonPath('name', 'Kind Of Tired');

        $this->assertSame(['Kind Of Tired'], $user->customMoods()->pluck('name')->all());
    }

    public function test_mood_impacts_reject_symbols(): void
    {
        $user = $this->user();
        $user->forceFill(['sex' => 'female', 'period_status' => 'not_yet'])->save();

        $this->postJson('/api/v1/mood-logs', [
            'mood' => MoodLog::MOODS[0],
            'intensity' => 3,
            'impacts' => ['work', 'rainy ## day'],
            'log_date' => now()->toDateString(),
        ])->assertStatus(422)->assertJsonValidationErrors('impacts.1');

        $this->postJson('/api/v1/mood-logs', [
            'mood' => MoodLog::MOODS[0],
            'intensity' => 3,
            'impacts' => ['work stress'],
            'log_date' => now()->toDateString(),
        ])->assertCreated()->assertJsonPath('impacts', ['Work Stress']);
    }

    public function test_a_label_starts_with_a_letter_while_a_dose_leads_with_its_number(): void
    {
        $user = $this->user();

        $this->postJson('/api/v1/meal-logs', [
            'meal_type' => 'lunch',
            'food_name' => '2 bottles of water',
            'log_date' => now()->toDateString(),
        ])->assertStatus(422)->assertJsonValidationErrors('food_name');

        $this->postJson('/api/v1/meal-logs', [
            'meal_type' => 'lunch',
            'food_name' => 'chicken 65',
            'log_date' => now()->toDateString(),
        ])->assertCreated()->assertJsonPath('food_name', 'Chicken 65');

        $this->postJson('/api/v1/medications', [
            'name' => 'Metformin',
            'dosage' => '2 tablets',
            'start_date' => now()->toDateString(),
        ])->assertCreated()->assertJsonPath('dosage', '2 tablets');

        $this->assertSame(1, $user->mealLogs()->count());
    }

    public function test_free_text_fields_still_accept_anything_the_user_writes(): void
    {
        $user = $this->user();
        $user->forceFill(['sex' => 'female', 'period_status' => 'not_yet'])->save();

        $this->postJson('/api/v1/appointments', [
            'doctor_name' => 'Rhea Vance',
            'appointment_at' => now()->addDay()->toDateTimeString(),
            'reason' => '3-month follow-up 🎉 (bring #12 labs!)',
        ])->assertCreated();

        $this->postJson('/api/v1/medications', [
            'name' => 'Ibuprofen',
            'start_date' => now()->toDateString(),
            'notes' => 'Take with food — see 100mg #pack https://x.com/a',
        ])->assertCreated();

        $this->postJson('/api/v1/mood-logs', [
            'mood' => MoodLog::MOODS[0],
            'intensity' => 3,
            'reflection' => 'A day: 44% work, 56% ☕. "Quote" & (paren).',
            'log_date' => now()->toDateString(),
        ])->assertCreated();
    }

    public function test_profile_names_reject_digits_and_are_stored_title_cased(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/profile', [
            'first_name' => "o'brien",
            'last_name' => 'smith-jones  marcus',
        ])->assertCreated()
            ->assertJsonPath('first_name', "O'brien")
            ->assertJsonPath('last_name', 'Smith-Jones Marcus');

        $this->postJson('/api/v1/profile', ['first_name' => 'j0n'])
            ->assertStatus(422)->assertJsonValidationErrors('first_name');
    }

    public function test_registration_accepts_apostrophes_and_hyphens_in_names(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'first_name' => 'maria d’cruz',
            'last_name' => 'smith-jones',
            'username' => 'maria_szj',
            'email' => 'maria@example.com',
            'password' => 'password123',
            'sex' => 'female',
            'consent' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('user.first_name', 'Maria D’cruz')
            ->assertJsonPath('user.last_name', 'Smith-Jones');

        $this->postJson('/api/v1/register', [
            'first_name' => 'Maria123',
            'last_name' => 'Smith',
            'username' => 'maria_two',
            'email' => 'maria2@example.com',
            'password' => 'password123',
            'sex' => 'female',
            'consent' => true,
        ])->assertStatus(422)->assertJsonValidationErrors('first_name');
    }

    public function test_numeric_fields_reject_out_of_range_values(): void
    {
        $this->user();

        $this->postJson('/api/v1/walking-logs', [
            'steps' => 9999999,
            'log_date' => now()->toDateString(),
        ])->assertStatus(422)->assertJsonValidationErrors('steps');

        $this->postJson('/api/v1/meal-logs', [
            'meal_type' => 'lunch',
            'food_name' => 'Rice bowl',
            'calories' => 99999,
            'log_date' => now()->toDateString(),
        ])->assertStatus(422)->assertJsonValidationErrors('calories');

        $this->postJson('/api/v1/weight-logs', [
            'weight_kg' => 900,
            'height_cm' => 400,
            'log_date' => now()->toDateString(),
        ])->assertStatus(422)->assertJsonValidationErrors(['weight_kg', 'height_cm']);

        $this->postJson('/api/v1/profile', [
            'first_name' => 'Ana',
            'last_name' => 'Cruz',
            'water_goal_ml' => 999999,
        ])->assertStatus(422)->assertJsonValidationErrors('water_goal_ml');
    }
}
