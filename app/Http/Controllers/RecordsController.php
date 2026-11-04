<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;

class RecordsController extends Controller
{
    private function sleepHours($log): float
    {
        if ($log->bedtime && $log->wake_time) {
            try {
                $bed = Carbon::parse($log->bedtime);
                $wake = Carbon::parse($log->wake_time);

                if ($wake->lessThanOrEqualTo($bed)) {
                    $wake->addDay();
                }

                return round($bed->diffInMinutes($wake) / 60, 2);
            } catch (\Throwable $exception) {
                // Fall back to the stored hours when the time values are incomplete.
            }
        }

        return round((float) ($log->hours ?? 0), 2);
    }

    private function formatHours($hours): string
    {
        $totalMinutes = (int) round(((float) $hours) * 60);

        if ($totalMinutes <= 0) {
            return '0h';
        }

        $hoursPart = intdiv($totalMinutes, 60);
        $minutesPart = $totalMinutes % 60;

        if ($hoursPart && $minutesPart) {
            return $hoursPart.'h '.$minutesPart.'m';
        }

        return $hoursPart ? $hoursPart.'h' : $minutesPart.'m';
    }

    private function formatWater($millilitres): string
    {
        $millilitres = (float) $millilitres;

        if ($millilitres >= 1000) {
            return rtrim(rtrim(number_format($millilitres / 1000, 1), '0'), '.').' L';
        }

        return (int) round($millilitres).' ml';
    }

    private function formatSteps($steps): string
    {
        return number_format((float) $steps).' steps';
    }

    private function shortDate($date): string
    {
        try {
            return Carbon::parse($date)->format('M j');
        } catch (\Throwable $exception) {
            return '';
        }
    }

    private function dateString($date): ?string
    {
        if (! $date) {
            return null;
        }

        try {
            return Carbon::parse($date)->toDateString();
        } catch (\Throwable $exception) {
            return null;
        }
    }

    private function feature(
        string $key,
        string $label,
        string $icon,
        string $tone,
        string $href,
        int $count,
        ?string $date = null,
        ?string $value = null,
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'icon' => $icon,
            'tone' => $tone,
            'href' => $href,
            'count' => $count,
            'last_date' => $date,
            'last_value' => $value,
        ];
    }

    public function summary(Request $request)
    {
        $user = $request->user();

        $latestWaterDay = $user->dailyLogs()->orderByDesc('log_date')->first();
        $latestWater = $latestWaterDay
            ? $user->dailyLogs()->whereDate('log_date', $latestWaterDay->log_date)->sum('water_ml')
            : 0;

        $latestSleep = $user->sleepLogs()->orderByDesc('log_date')->orderByDesc('id')->first();
        $latestWalking = $user->walkingLogs()->orderByDesc('log_date')->orderByDesc('id')->first();
        $latestMeal = $user->mealLogs()->orderByDesc('log_date')->orderByDesc('id')->first();
        $latestWeight = $user->weightLogs()->orderByDesc('log_date')->orderByDesc('id')->first();
        $latestMood = $user->moodLogs()->orderByDesc('log_date')->orderByDesc('id')->first();
        $latestSymptom = $user->symptomLogs()->orderByDesc('log_date')->orderByDesc('id')->first();
        $latestVitals = $user->vitalSignLogs()->orderByDesc('log_date')->orderByDesc('id')->first();
        $latestPeriod = $user->periodLogs()->orderByDesc('start_date')->first();
        $latestMedication = $user->medications()->orderByDesc('created_at')->first();
        $latestAppointment = $user->appointments()->orderByDesc('appointment_at')->first();
        $latestDocument = $user->documents()->orderByDesc('created_at')->first();

        $features = [
            $this->feature('water', 'Water', 'i-droplet', 'blue', '/water',
                $user->dailyLogs()->count(),
                $this->dateString($latestWaterDay?->log_date),
                $latestWater > 0 ? $this->formatWater($latestWater) : null),
            $this->feature('sleep', 'Sleep', 'i-moon', 'violet', '/sleep',
                $user->sleepLogs()->count(),
                $this->dateString($latestSleep?->log_date),
                $latestSleep ? $this->formatHours($this->sleepHours($latestSleep)) : null),
            $this->feature('walking', 'Activity', 'i-activity', 'amber', '/walking',
                $user->walkingLogs()->count(),
                $this->dateString($latestWalking?->log_date),
                $latestWalking ? $this->formatSteps($latestWalking->steps) : null),
            $this->feature('meals', 'Meals', 'i-meal', 'mint', '/meals',
                $user->mealLogs()->count(),
                $this->dateString($latestMeal?->log_date),
                $latestMeal?->food_name),
            $this->feature('weight', 'Weight', 'i-scale', 'slate', '/weight',
                $user->weightLogs()->count(),
                $this->dateString($latestWeight?->log_date),
                $latestWeight ? number_format((float) $latestWeight->weight_kg, 1).' kg' : null),
            $this->feature('mood', 'Mood', 'i-smile', 'gold', '/mood',
                $user->moodLogs()->count(),
                $this->dateString($latestMood?->log_date),
                $latestMood ? ucfirst($latestMood->mood) : null),
            $this->feature('symptoms', 'Symptoms', 'i-thermometer', 'amber', '/symptoms',
                $user->symptomLogs()->count(),
                $this->dateString($latestSymptom?->log_date),
                $latestSymptom?->symptom),
            $this->feature('vitals', 'Vitals', 'i-heart', 'rose', '/vitals',
                $user->vitalSignLogs()->count(),
                $this->dateString($latestVitals?->log_date),
                $latestVitals ? $latestVitals->systolic.'/'.$latestVitals->diastolic.' mmHg' : null),
            $this->feature('medications', 'Medications', 'i-pill', 'plum', '/medications',
                $user->medications()->count(),
                $this->dateString($latestMedication?->start_date),
                $latestMedication?->name),
            $this->feature('appointments', 'Appointments', 'i-calendar', 'blue', '/appointments',
                $user->appointments()->count(),
                $this->dateString($latestAppointment?->appointment_at),
                $latestAppointment?->doctor_name),
            $this->feature('documents', 'Documents', 'i-file', 'plum', '/documents',
                $user->documents()->count(),
                $this->dateString($latestDocument?->created_at),
                $latestDocument?->title),
        ];

        if ($user->show_period_tracker) {
            $features[] = $this->feature('period', 'Cycle', 'i-cycle', 'rose', '/period',
                $user->periodLogs()->count(),
                $this->dateString($latestPeriod?->start_date),
                $latestPeriod ? 'Started '.$this->shortDate($latestPeriod->start_date) : null);
        }

        $recent = collect($features)
            ->filter(fn (array $feature) => $feature['last_date'])
            ->map(fn (array $feature) => [
                'key' => $feature['key'],
                'label' => $feature['label'],
                'icon' => $feature['icon'],
                'tone' => $feature['tone'],
                'href' => $feature['href'],
                'date' => $feature['last_date'],
                'summary' => $feature['last_value'],
            ])
            ->sortByDesc('date')
            ->take(8)
            ->values();

        return response()->json([
            'total_records' => (int) collect($features)->sum('count'),
            'features_used' => count(array_filter(array_column($features, 'count'))),
            'features' => $features,
            'recent' => $recent,
        ]);
    }

    public function day(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'date' => ['required', 'date'],
        ]);

        $date = Carbon::parse($validated['date'])->toDateString();

        $water = (float) $user->dailyLogs()->whereDate('log_date', $date)->sum('water_ml');
        $sleep = $user->sleepLogs()->whereDate('log_date', $date)
            ->orderBy('id')->get(['id', 'hours', 'bedtime', 'wake_time']);
        $walking = $user->walkingLogs()->whereDate('log_date', $date)->orderBy('id')->get();
        $meals = $user->mealLogs()->whereDate('log_date', $date)->orderBy('id')->get();
        $weight = $user->weightLogs()->whereDate('log_date', $date)->orderBy('id')->get();
        $moods = $user->moodLogs()->whereDate('log_date', $date)->orderBy('id')->get();
        $symptoms = $user->symptomLogs()->whereDate('log_date', $date)->orderBy('id')->get();
        $vitals = $user->vitalSignLogs()->whereDate('log_date', $date)->orderBy('id')->get();
        $period = $user->periodLogs()
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->orderBy('id')->get();
        $medications = $user->medications()
            ->where('is_active', true)
            ->whereDate('start_date', '<=', $date)
            ->where(function ($query) use ($date) {
                $query->whereNull('end_date')->orWhereDate('end_date', '>=', $date);
            })
            ->orderBy('name')->get();
        $appointments = $user->appointments()->whereDate('appointment_at', $date)->orderBy('appointment_at')->get();
        $documents = $user->documents()->whereDate('created_at', $date)->orderBy('created_at')->get();

        return response()->json([
            'date' => $date,
            'water' => [
                'total_ml' => round($water, 1),
                'label' => $this->formatWater($water),
            ],
            'sleep' => $sleep->map(fn ($log) => [
                'id' => $log->id,
                'hours' => $this->sleepHours($log),
                'label' => $this->formatHours($this->sleepHours($log)),
                'bedtime' => $log->bedtime,
                'wake_time' => $log->wake_time,
            ]),
            'walking' => $walking->map(fn ($log) => [
                'id' => $log->id,
                'activity_type' => $log->activity_type,
                'steps' => $log->steps,
                'minutes' => $log->minutes,
                'label' => $this->formatSteps($log->steps).($log->minutes ? ' · '.$log->minutes.' min' : ''),
            ]),
            'meals' => $meals->map(fn ($log) => [
                'id' => $log->id,
                'meal_type' => $log->meal_type,
                'food_name' => $log->food_name,
                'calories' => $log->calories,
                'label' => $log->food_name.($log->calories ? ' · '.$log->calories.' kcal' : ''),
            ]),
            'weight' => $weight->map(fn ($log) => [
                'id' => $log->id,
                'weight_kg' => $log->weight_kg,
                'label' => number_format((float) $log->weight_kg, 1).' kg',
            ]),
            'moods' => $moods->map(fn ($log) => [
                'id' => $log->id,
                'mood' => $log->mood,
                'intensity' => $log->intensity,
                'label' => ucfirst($log->mood).($log->intensity ? ' · '.$log->intensity.'/5' : ''),
            ]),
            'symptoms' => $symptoms->map(fn ($log) => [
                'id' => $log->id,
                'symptom' => $log->symptom,
                'severity' => $log->severity,
                'label' => $log->symptom.($log->severity ? ' · '.ucfirst($log->severity) : ''),
            ]),
            'vitals' => $vitals->map(fn ($log) => [
                'id' => $log->id,
                'systolic' => $log->systolic,
                'diastolic' => $log->diastolic,
                'heart_rate' => $log->heart_rate,
                'label' => ($log->systolic ? $log->systolic.'/'.$log->diastolic : '')
                    .($log->heart_rate ? ($log->systolic ? ' · ' : '').$log->heart_rate.' bpm' : ''),
            ]),
            'period' => $period->map(fn ($log) => [
                'id' => $log->id,
                'start_date' => $log->start_date->toDateString(),
                'end_date' => $log->end_date?->toDateString(),
                'flow' => $log->flow,
                'label' => 'Period day'.($log->flow ? ' · '.ucfirst($log->flow) : ''),
            ]),
            'medications' => $medications->map(fn ($medication) => [
                'id' => $medication->id,
                'name' => $medication->name,
                'dosage' => $medication->dosage,
                'times' => $medication->times ?? [],
                'label' => $medication->name.($medication->dosage ? ' · '.$medication->dosage : ''),
            ]),
            'appointments' => $appointments->map(fn ($appointment) => [
                'id' => $appointment->id,
                'doctor_name' => $appointment->doctor_name,
                'specialty' => $appointment->specialty,
                'appointment_at' => $appointment->appointment_at?->toIso8601String(),
                'label' => ($appointment->appointment_at ? $appointment->appointment_at->format('g:i A').' · ' : '')
                    .$appointment->doctor_name,
            ]),
            'documents' => $documents->map(fn ($document) => [
                'id' => $document->id,
                'title' => $document->title,
                'type' => $document->type,
                'label' => $document->title ?: $document->original_name,
            ]),
        ]);
    }
}
