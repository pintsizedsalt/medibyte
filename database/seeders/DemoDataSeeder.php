<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\DailyLog;
use App\Models\Document;
use App\Models\MealLog;
use App\Models\Medication;
use App\Models\MoodLog;
use App\Models\PeriodLog;
use App\Models\SleepLog;
use App\Models\SymptomLog;
use App\Models\User;
use App\Models\VitalSignLog;
use App\Models\WalkingLog;
use App\Models\WeightLog;
use App\Notifications\HealthReminder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $female = User::updateOrCreate(
            ['email' => 'demo.female@medibyte.test'],
            [
                'name' => 'Demo Female',
                'first_name' => 'Demo',
                'last_name' => 'Female',
                'username' => 'demo_female',
                'password' => Hash::make('password'),
                'sex' => 'female',
                'period_status' => 'active',
                'weight_goal' => 60,
                'water_goal_ml' => 2000,
                'sleep_goal_hours' => 8,
                'walking_goal_steps' => 8000,
                'calorie_goal' => 2000,
                'cycle_length' => 28,
                'reminders_enabled' => true,
                'onboarding_completed_at' => now(),
            ]
        );
        $female->forceFill(['consented_at' => now()])->save();

        $male = User::updateOrCreate(
            ['email' => 'demo.male@medibyte.test'],
            [
                'name' => 'Demo Male',
                'first_name' => 'Demo',
                'last_name' => 'Male',
                'username' => 'demo_male',
                'password' => Hash::make('password'),
                'sex' => 'male',
                'period_status' => null,
                'weight_goal' => 75,
                'water_goal_ml' => 2500,
                'sleep_goal_hours' => 7.5,
                'walking_goal_steps' => 10000,
                'calorie_goal' => 2400,
                'reminders_enabled' => true,
                'onboarding_completed_at' => now(),
            ]
        );
        $male->forceFill(['consented_at' => now()])->save();

        $this->seedFemaleData($female);
        $this->seedMaleData($male);
    }

    private function seedFemaleData(User $user): void
    {
        $date = today()->toDateString();

        DailyLog::updateOrCreate(
            ['user_id' => $user->id, 'log_date' => $date],
            ['water_ml' => 1500]
        );
        SleepLog::updateOrCreate(
            ['user_id' => $user->id, 'log_date' => $date],
            ['hours' => 7.5, 'bedtime' => '23:00', 'wake_time' => '06:30']
        );
        WalkingLog::updateOrCreate(
            ['user_id' => $user->id, 'log_date' => $date],
            ['activity_type' => 'walking', 'steps' => 5200, 'minutes' => 42]
        );
        MealLog::updateOrCreate(
            ['user_id' => $user->id, 'log_date' => $date, 'meal_type' => 'breakfast'],
            ['food_name' => 'Oatmeal with banana', 'calories' => 350]
        );
        MealLog::updateOrCreate(
            ['user_id' => $user->id, 'log_date' => $date, 'meal_type' => 'lunch'],
            ['food_name' => 'Chicken rice bowl', 'calories' => 600]
        );
        WeightLog::updateOrCreate(
            ['user_id' => $user->id, 'log_date' => $date],
            ['weight_kg' => 62.5, 'height_cm' => 165]
        );
        MoodLog::updateOrCreate(
            ['user_id' => $user->id, 'log_date' => $date],
            ['mood' => 'calm', 'intensity' => 4]
        );

        $history = [
            6 => ['water' => 1700, 'sleep' => 8.0, 'steps' => 6100, 'mood' => 'content'],
            5 => ['water' => 1800, 'sleep' => 7.0, 'steps' => 8000, 'mood' => 'calm'],
            4 => ['water' => 2000, 'sleep' => 8.0, 'steps' => 4200, 'mood' => 'tired'],
            3 => ['water' => 1500, 'sleep' => 7.5, 'steps' => 7000, 'mood' => 'calm'],
            2 => ['water' => 1600, 'sleep' => 8.5, 'steps' => 5600, 'mood' => 'motivated'],
            1 => ['water' => 1900, 'sleep' => 7.5, 'steps' => 6800, 'mood' => 'content'],
        ];

        foreach ($history as $daysAgo => $entry) {
            $historyDate = today()->subDays($daysAgo)->toDateString();

            DailyLog::updateOrCreate(
                ['user_id' => $user->id, 'log_date' => $historyDate],
                ['water_ml' => $entry['water']]
            );
            SleepLog::updateOrCreate(
                ['user_id' => $user->id, 'log_date' => $historyDate],
                ['hours' => $entry['sleep']]
            );
            WalkingLog::updateOrCreate(
                ['user_id' => $user->id, 'log_date' => $historyDate],
                ['activity_type' => 'walking', 'steps' => $entry['steps'], 'minutes' => 30]
            );
            MoodLog::updateOrCreate(
                ['user_id' => $user->id, 'log_date' => $historyDate],
                ['mood' => $entry['mood'], 'intensity' => 4]
            );
        }

        SymptomLog::updateOrCreate(
            ['user_id' => $user->id, 'log_date' => $date, 'symptom' => 'Headache'],
            ['illness_name' => 'Tension headache', 'severity' => 2, 'notes' => 'After a long screen session.']
        );
        VitalSignLog::updateOrCreate(
            ['user_id' => $user->id, 'log_date' => $date],
            [
                'systolic' => 118,
                'diastolic' => 76,
                'heart_rate' => 72,
                'temperature_c' => 36.6,
                'oxygen_saturation' => 98,
            ]
        );

        foreach ([56, 28, 3] as $daysAgo) {
            $start = today()->subDays($daysAgo);
            PeriodLog::updateOrCreate(
                ['user_id' => $user->id, 'start_date' => $start->toDateString()],
                ['end_date' => $start->copy()->addDays(4)->toDateString(), 'flow' => 'medium']
            );
        }

        Medication::updateOrCreate(
            ['user_id' => $user->id, 'name' => 'Paracetamol'],
            [
                'dosage' => '500 mg',
                'food_instruction' => 'after_meal',
                'times' => ['08:00', '20:00'],
                'start_date' => today()->toDateString(),
                'is_active' => true,
            ]
        );

        Appointment::updateOrCreate(
            ['user_id' => $user->id, 'doctor_name' => 'Dr. Ana Reyes'],
            [
                'specialty' => 'Internal medicine',
                'hospital' => 'MediByte Demo Clinic',
                'appointment_at' => now()->addDay()->setTime(9, 0),
                'reason' => 'Annual check-up',
                'status' => 'scheduled',
            ]
        );

        $path = 'documents/'.$user->id.'/demo-lab-result.pdf';
        $pdf = "%PDF-1.4\n1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj\n3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 300 200] /Contents 4 0 R >> endobj\n4 0 obj << /Length 44 >> stream\nBT /F1 18 Tf 40 100 Td (MediByte demo result) Tj ET\nendstream endobj\nxref\n0 5\n0000000000 65535 f \ntrailer << /Root 1 0 R /Size 5 >>\nstartxref\n0\n%%EOF\n";
        Storage::disk('local')->put($path, $pdf);

        Document::updateOrCreate(
            ['user_id' => $user->id, 'title' => 'Demo lab result'],
            [
                'type' => 'lab_result',
                'original_name' => 'demo-lab-result.pdf',
                'path' => $path,
                'mime_type' => 'application/pdf',
                'size' => strlen($pdf),
                'notes' => 'Seeded sample document for the demo account.',
            ]
        );

        if ($user->notifications()->count() === 0) {
            $user->notify(new HealthReminder([
                'dedupe_key' => 'demo:welcome',
                'kind' => 'welcome',
                'title' => 'Welcome to MediByte',
                'body' => 'Your demo health data is ready. Explore the dashboard and try logging an entry.',
                'url' => '/dashboard',
            ]));
        }
    }

    private function seedMaleData(User $user): void
    {
        $date = today()->toDateString();

        DailyLog::updateOrCreate(
            ['user_id' => $user->id, 'log_date' => $date],
            ['water_ml' => 2000]
        );
        SleepLog::updateOrCreate(
            ['user_id' => $user->id, 'log_date' => $date],
            ['hours' => 7, 'bedtime' => '23:30', 'wake_time' => '06:30']
        );
        WalkingLog::updateOrCreate(
            ['user_id' => $user->id, 'log_date' => $date],
            ['activity_type' => 'running', 'steps' => 7600, 'minutes' => 35]
        );
        WeightLog::updateOrCreate(
            ['user_id' => $user->id, 'log_date' => $date],
            ['weight_kg' => 78, 'height_cm' => 178]
        );
        MoodLog::updateOrCreate(
            ['user_id' => $user->id, 'log_date' => $date],
            ['mood' => 'motivated', 'intensity' => 4]
        );

        $history = [
            6 => ['water' => 2500, 'sleep' => 7.5, 'steps' => 9000, 'mood' => 'calm'],
            5 => ['water' => 2400, 'sleep' => 6.5, 'steps' => 10500, 'mood' => 'tired'],
            4 => ['water' => 2200, 'sleep' => 8.0, 'steps' => 6000, 'mood' => 'content'],
            3 => ['water' => 2600, 'sleep' => 7.0, 'steps' => 8200, 'mood' => 'energetic'],
            2 => ['water' => 2000, 'sleep' => 7.5, 'steps' => 9800, 'mood' => 'calm'],
            1 => ['water' => 2300, 'sleep' => 7.0, 'steps' => 8700, 'mood' => 'motivated'],
        ];

        foreach ($history as $daysAgo => $entry) {
            $historyDate = today()->subDays($daysAgo)->toDateString();

            DailyLog::updateOrCreate(
                ['user_id' => $user->id, 'log_date' => $historyDate],
                ['water_ml' => $entry['water']]
            );
            SleepLog::updateOrCreate(
                ['user_id' => $user->id, 'log_date' => $historyDate],
                ['hours' => $entry['sleep']]
            );
            WalkingLog::updateOrCreate(
                ['user_id' => $user->id, 'log_date' => $historyDate],
                ['activity_type' => 'running', 'steps' => $entry['steps'], 'minutes' => 35]
            );
            MoodLog::updateOrCreate(
                ['user_id' => $user->id, 'log_date' => $historyDate],
                ['mood' => $entry['mood'], 'intensity' => 4]
            );
        }
    }
}
