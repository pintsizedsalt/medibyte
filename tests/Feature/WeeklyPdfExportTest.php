<?php

namespace Tests\Feature;

use App\Models\Medication;
use App\Models\User;
use App\Support\WeeklyReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The weekly PDF a user hands to a clinic: it has to be a real PDF, it has to
 * cover exactly the last seven days, and it must be sent as a download.
 */
class WeeklyPdfExportTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_the_weekly_export_downloads_as_a_pdf(): void
    {
        $this->user();

        $response = $this->getJson('/api/v1/account/export/pdf')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertSame('attachment; filename="medibyte-weekly-summary-'.now()->format('Y-m-d').'.pdf"', $response->headers->get('Content-Disposition'));

        $pdf = $response->getContent();

        $this->assertStringStartsWith('%PDF-1.4', $pdf);
        $this->assertStringEndsWith('%%EOF', $pdf);
        $this->assertStringContainsString('/MediaBox [0 0 595.28 841.89]', $pdf);
    }

    public function test_the_cross_reference_offsets_point_at_the_xref_table(): void
    {
        $pdf = (new WeeklyReport($this->user()))->pdf();

        $start = (int) trim(substr($pdf, strrpos($pdf, 'startxref') + strlen('startxref')));

        $this->assertSame('xref', substr($pdf, $start, 4));

        // Every object the table claims must actually start at its offset.
        preg_match_all('/^(\d{10}) 00000 n $/m', $pdf, $offsets);

        foreach ($offsets[1] as $offset) {
            $this->assertMatchesRegularExpression('/^\d+ 0 obj/', substr($pdf, (int) $offset, 20));
        }
    }

    public function test_only_the_last_seven_days_are_reported(): void
    {
        $user = $this->user();

        $user->symptomLogs()->create([
            'symptom' => 'Headache',
            'severity' => 3,
            'log_date' => now()->toDateString(),
        ]);

        $user->symptomLogs()->create([
            'symptom' => 'Nausea',
            'severity' => 2,
            'log_date' => now()->subDays(30)->toDateString(),
        ]);

        $pdf = (new WeeklyReport($user))->pdf();

        $this->assertStringContainsString('Headache', $pdf);
        $this->assertStringNotContainsString('Nausea', $pdf);
    }

    public function test_the_logged_side_of_the_week_is_drawn(): void
    {
        $user = $this->user();
        $user->forceFill(['sex' => 'female', 'period_status' => 'active'])->save();

        $today = now()->toDateString();

        $user->dailyLogs()->create(['log_date' => $today, 'water_ml' => 1500]);
        $user->sleepLogs()->create(['log_date' => $today, 'hours' => 8, 'bedtime' => '22:30', 'wake_time' => '06:30']);
        $user->walkingLogs()->create(['log_date' => $today, 'activity_type' => 'walking', 'steps' => 9200]);
        $user->mealLogs()->create(['log_date' => $today, 'meal_type' => 'lunch', 'meal_time' => '12:15', 'food_name' => 'Rice bowl', 'calories' => 620]);
        $user->weightLogs()->create(['log_date' => $today, 'weight_kg' => 62.4, 'height_cm' => 165]);
        $user->vitalSignLogs()->create(['log_date' => $today, 'systolic' => 118, 'diastolic' => 76, 'heart_rate' => 68, 'temperature_c' => 36.8, 'oxygen_saturation' => 98]);
        $user->moodLogs()->create(['log_date' => $today, 'mood' => 'calm', 'intensity' => 4, 'impacts' => ['work stress']]);
        $user->periodLogs()->create(['start_date' => now()->subDays(2)->toDateString(), 'end_date' => $today, 'flow' => 'medium']);
        $user->medications()->create(['name' => 'Metformin', 'dosage' => '500 mg', 'times' => ['08:00', '20:00'], 'start_date' => now()->subMonth()->toDateString(), 'food_instruction' => 'with_meal']);
        $user->appointments()->create(['doctor_name' => 'Rhea Vance', 'specialty' => 'Endocrinology', 'hospital' => 'St. Luke\'s Medical Center', 'appointment_at' => now()->subDays(3), 'reason' => 'Three-month follow-up', 'status' => 'done']);

        $pdf = (new WeeklyReport($user))->pdf();

        foreach ([
            'MediByte Weekly Health Summary',
            'VITAL SIGNS',
            'WEIGHT AND BMI',
            'SYMPTOMS',
            'MEDICATIONS',
            'APPOINTMENTS',
            'MOOD',
            'CYCLE',
            '9,200 steps',
            '118/76 mmHg',
            '500 mg',
            'Rhea Vance',
            'St. Luke',
            'work stress',
        ] as $expected) {
            $this->assertStringContainsString($expected, $pdf);
        }

        $this->assertStringContainsString('Self-reported by the account holder', $pdf);
    }

    public function test_an_empty_week_still_exports_a_readable_report(): void
    {
        $user = $this->user();
        $user->forceFill(['sex' => 'male'])->save();

        $pdf = (new WeeklyReport($user))->pdf();

        $this->assertStringContainsString('Not logged', $pdf);
        $this->assertSame(['AT A GLANCE', 'THE WEEK, DAY BY DAY'], $this->sections($pdf));
    }

    public function test_only_the_kinds_with_records_get_a_section(): void
    {
        $user = $this->user();
        $today = now()->toDateString();

        $user->vitalSignLogs()->create(['log_date' => $today, 'systolic' => 118, 'diastolic' => 76]);
        $user->symptomLogs()->create(['symptom' => 'Headache', 'severity' => 3, 'log_date' => $today]);

        $pdf = (new WeeklyReport($user))->pdf();

        $this->assertStringContainsString('118/76 mmHg', $pdf);
        $this->assertStringContainsString('Headache', $pdf);

        $this->assertSame(['AT A GLANCE', 'THE WEEK, DAY BY DAY', 'VITAL SIGNS', 'SYMPTOMS'], $this->sections($pdf));
    }

    public function test_an_upcoming_appointment_is_reported_even_when_the_week_was_empty(): void
    {
        $user = $this->user();

        $user->appointments()->create([
            'doctor_name' => 'Rhea Vance',
            'appointment_at' => now()->addDays(9),
            'status' => 'scheduled',
        ]);

        $pdf = (new WeeklyReport($user))->pdf();

        $this->assertSame(['AT A GLANCE', 'THE WEEK, DAY BY DAY', 'APPOINTMENTS'], $this->sections($pdf));
        $this->assertStringContainsString('Next scheduled', $pdf);
    }

    public function test_each_dose_is_printed_with_its_unit(): void
    {
        $user = $this->user();

        $user->medications()->create([
            'name' => 'Levothyroxine',
            'dosage' => Medication::dosageText(0.25, 'mg'),
            'dosage_amount' => 0.25,
            'dosage_unit' => 'mg',
            'times' => ['07:00'],
            'start_date' => now()->subWeek()->toDateString(),
            'is_active' => true,
        ]);

        $pdf = (new WeeklyReport($user))->pdf();

        $this->assertStringContainsString('Levothyroxine', $pdf);
        $this->assertStringContainsString('0.25 mg', $pdf);
    }

    /**
     * The section titles a report paints, read back out of the PDF. Every heading
     * is drawn in bold nine point so they can be told from the table headers, which
     * are seven and a half, and the stat cards, which are seven.
     *
     * @return array<int, string>
     */
    private function sections(string $pdf): array
    {
        preg_match_all('/BT \/F2 9 Tf 1 0 0 1 [-\d.]+ [-\d.]+ Tm \(([^)]*)\) Tj ET/', $pdf, $headings);

        return $headings[1];
    }
}
