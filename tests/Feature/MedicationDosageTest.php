<?php

namespace Tests\Feature;

use App\Models\Medication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * A dose is recorded as an amount and a unit so a clinician reading the chart
 * never has to guess whether "10" means milligrams or micrograms.
 */
class MedicationDosageTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Levothyroxine',
            'start_date' => now()->toDateString(),
        ], $overrides);
    }

    public function test_an_amount_and_unit_are_stored_and_read_as_one_dose(): void
    {
        $this->postJson('/api/v1/medications', $this->payload([
            'dosage_amount' => 0.25,
            'dosage_unit' => 'mg',
        ]))->assertCreated()
            ->assertJsonPath('dosage_amount', 0.25)
            ->assertJsonPath('dosage_unit', 'mg')
            ->assertJsonPath('dosage', '0.25 mg');
    }

    public function test_whole_amounts_lose_their_padding_zeros(): void
    {
        $this->postJson('/api/v1/medications', $this->payload([
            'dosage_amount' => '10.000',
            'dosage_unit' => 'mg',
        ]))->assertCreated()->assertJsonPath('dosage', '10 mg');

        $this->postJson('/api/v1/medications', $this->payload([
            'name' => 'Vitamin D',
            'dosage_amount' => '1000',
            'dosage_unit' => 'IU',
        ]))->assertCreated()->assertJsonPath('dosage', '1000 IU');
    }

    public function test_an_amount_without_a_unit_is_rejected(): void
    {
        $this->postJson('/api/v1/medications', $this->payload(['dosage_amount' => 5]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('dosage_unit');

        $this->assertDatabaseCount('medications', 0);
    }

    public function test_a_unit_outside_the_list_is_rejected(): void
    {
        $this->postJson('/api/v1/medications', $this->payload([
            'dosage_amount' => 5,
            'dosage_unit' => 'scoop',
        ]))->assertStatus(422)->assertJsonValidationErrors('dosage_unit');
    }

    public function test_an_amount_finer_than_a_thousandth_is_rejected(): void
    {
        $this->postJson('/api/v1/medications', $this->payload([
            'dosage_amount' => 0.1255,
            'dosage_unit' => 'mg',
        ]))->assertStatus(422)->assertJsonValidationErrors('dosage_amount');
    }

    public function test_a_free_text_dose_from_the_setup_wizard_is_still_recorded(): void
    {
        $this->postJson('/api/v1/medications', $this->payload(['dosage' => 'as directed']))
            ->assertCreated()
            ->assertJsonPath('dosage', 'as directed')
            ->assertJsonPath('dosage_amount', null)
            ->assertJsonPath('dosage_unit', null);
    }

    public function test_clearing_both_dose_fields_clears_the_saved_dose(): void
    {
        $medication = Medication::create([
            'user_id' => $this->user->id,
            'name' => 'Metformin',
            'dosage' => '500 mg',
            'dosage_amount' => 500,
            'dosage_unit' => 'mg',
            'start_date' => now()->toDateString(),
        ]);

        $this->putJson("/api/v1/medications/{$medication->id}", $this->payload([
            'dosage_amount' => '',
            'dosage_unit' => '',
        ]))->assertOk()
            ->assertJsonPath('dosage', null)
            ->assertJsonPath('dosage_amount', null)
            ->assertJsonPath('dosage_unit', null);
    }

    public function test_the_dose_is_replaced_rather_than_tacked_onto_the_previous_one(): void
    {
        $medication = Medication::create([
            'user_id' => $this->user->id,
            'name' => 'Amlodipine',
            'dosage' => '5 mg',
            'dosage_amount' => 5,
            'dosage_unit' => 'mg',
            'start_date' => now()->toDateString(),
        ]);

        $this->putJson("/api/v1/medications/{$medication->id}", $this->payload([
            'dosage_amount' => 10,
            'dosage_unit' => 'mg',
        ]))->assertOk()->assertJsonPath('dosage', '10 mg');
    }

    public function test_the_pages_offer_exactly_the_units_the_server_accepts(): void
    {
        $records = $this->unitsIn(resource_path('views/medications.blade.php'), "/'dose', (\[[^\]]*\])/");
        $wizard = $this->unitsIn(resource_path('views/profile.blade.php'), '/const DOSE_UNITS = (\[[^\]]*\])/');

        $this->assertNotEmpty($records);
        $this->assertSame(Medication::UNITS, $records);
        $this->assertSame(Medication::UNITS, $wizard);
    }

    /**
     * @return array<int, string>
     */
    private function unitsIn(string $path, string $pattern): array
    {
        $this->assertMatchesRegularExpression($pattern, (string) file_get_contents($path));

        preg_match($pattern, (string) file_get_contents($path), $matches);

        preg_match_all("/'([^']+)'/", $matches[1], $units);

        return $units[1];
    }
}
