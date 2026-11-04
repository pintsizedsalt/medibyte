<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SearchController extends Controller
{
    /**
     * App sections that can be found from the global search bar.
     *
     * @var array<int, array{label: string, description: string, href: string, icon: string, tone: string, keywords: array<int, string>}>
     */
    private const FEATURES = [
        ['label' => 'Dashboard', 'description' => 'Health summary and quick logs', 'href' => '/dashboard', 'icon' => 'home', 'tone' => 'blue', 'keywords' => ['home', 'overview', 'summary']],
        ['label' => 'All records', 'description' => 'Every entry in one place', 'href' => '/records', 'icon' => 'file', 'tone' => 'blue', 'keywords' => ['history', 'records', 'log']],
        ['label' => 'Water', 'description' => 'Hydration and daily intake', 'href' => '/water', 'icon' => 'droplet', 'tone' => 'blue', 'keywords' => ['hydration', 'drink', 'glass', 'ml']],
        ['label' => 'Sleep', 'description' => 'Bedtime, wake-up, and hours slept', 'href' => '/sleep', 'icon' => 'moon', 'tone' => 'violet', 'keywords' => ['rest', 'bedtime', 'wake', 'night']],
        ['label' => 'Activity', 'description' => 'Steps, walking, and active minutes', 'href' => '/walking', 'icon' => 'activity', 'tone' => 'amber', 'keywords' => ['walking', 'steps', 'exercise', 'move', 'movement']],
        ['label' => 'Meals', 'description' => 'Food diary and calories', 'href' => '/meals', 'icon' => 'meal', 'tone' => 'mint', 'keywords' => ['food', 'eat', 'breakfast', 'lunch', 'dinner', 'snack', 'calories']],
        ['label' => 'Weight & BMI', 'description' => 'Weight history and BMI', 'href' => '/weight', 'icon' => 'scale', 'tone' => 'slate', 'keywords' => ['bmi', 'kg', 'weight']],
        ['label' => 'Mood', 'description' => 'Mood check-ins and custom moods', 'href' => '/mood', 'icon' => 'smile', 'tone' => 'gold', 'keywords' => ['feeling', 'emotion', 'check-in', 'custom mood']],
        ['label' => 'Symptoms', 'description' => 'Symptom and severity log', 'href' => '/symptoms', 'icon' => 'thermometer', 'tone' => 'amber', 'keywords' => ['illness', 'severity', 'pain', 'sick']],
        ['label' => 'Vitals', 'description' => 'Blood pressure, heart rate, and more', 'href' => '/vitals', 'icon' => 'heart', 'tone' => 'rose', 'keywords' => ['blood pressure', 'heart rate', 'temperature', 'oxygen', 'bp']],
        ['label' => 'Menstrual cycle', 'description' => 'Periods, flow, and predictions', 'href' => '/period', 'icon' => 'cycle', 'tone' => 'rose', 'keywords' => ['period', 'menstrual', 'cycle', 'pregnancy']],
        ['label' => 'Medications', 'description' => 'Dosage, schedule, and reminders', 'href' => '/medications', 'icon' => 'pill', 'tone' => 'plum', 'keywords' => ['medicine', 'pill', 'dose', 'prescription']],
        ['label' => 'Appointments', 'description' => 'Doctor visits and schedules', 'href' => '/appointments', 'icon' => 'calendar', 'tone' => 'blue', 'keywords' => ['doctor', 'visit', 'schedule', 'clinic']],
        ['label' => 'Documents', 'description' => 'Prescriptions, lab results, certificates', 'href' => '/documents', 'icon' => 'file', 'tone' => 'plum', 'keywords' => ['files', 'upload', 'lab result', 'certificate']],
        ['label' => 'Notifications', 'description' => 'Reminders and account updates', 'href' => '/notifications', 'icon' => 'bell', 'tone' => 'violet', 'keywords' => ['inbox', 'alerts', 'reminders']],
        ['label' => 'Settings', 'description' => 'Reminders, privacy, and account', 'href' => '/settings', 'icon' => 'settings', 'tone' => 'slate', 'keywords' => ['account', 'privacy', 'delete', 'export']],
        ['label' => 'Profile', 'description' => 'Personal details and daily goals', 'href' => '/profile', 'icon' => 'user', 'tone' => 'mint', 'keywords' => ['account', 'goals', 'targets', 'name']],
    ];

    public function __invoke(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));
        $user = $request->user();

        if (Str::length($query) < 1) {
            return response()->json([
                'query' => '',
                'features' => array_slice(self::FEATURES, 0, 6),
                'meals' => [],
                'documents' => [],
            ]);
        }

        $needle = Str::lower($query);

        $features = collect(self::FEATURES)
            ->filter(function (array $feature) use ($needle) {
                $haystack = Str::lower(
                    $feature['label'].' '.$feature['description'].' '.implode(' ', $feature['keywords'])
                );

                return Str::contains($haystack, $needle);
            })
            ->take(6)
            ->map(fn (array $feature) => collect($feature)->except('keywords')->all())
            ->values();

        $meals = $user->mealLogs()
            ->where(function ($builder) use ($query) {
                $builder->where('food_name', 'like', '%'.$query.'%')
                    ->orWhere('meal_type', 'like', '%'.$query.'%');
            })
            ->latest('log_date')
            ->limit(6)
            ->get()
            ->map(fn ($meal) => [
                'id' => $meal->id,
                'title' => $meal->food_name,
                'subtitle' => collect([
                    Str::title($meal->meal_type),
                    $meal->meal_time ? substr((string) $meal->meal_time, 0, 5) : null,
                    $meal->calories !== null ? $meal->calories.' cal' : null,
                ])->filter()->implode(' · '),
                'href' => '/meals?date='.$meal->log_date->toDateString(),
                'icon' => 'meal',
                'tone' => 'mint',
                'date' => $meal->log_date?->toDateString(),
            ]);

        $documents = $user->documents()
            ->where(function ($builder) use ($query) {
                $builder->where('title', 'like', '%'.$query.'%')
                    ->orWhere('original_name', 'like', '%'.$query.'%')
                    ->orWhere('notes', 'like', '%'.$query.'%');
            })
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn ($document) => [
                'id' => $document->id,
                'title' => $document->title,
                'subtitle' => $document->original_name,
                'href' => '/documents',
                'icon' => 'file',
                'tone' => 'plum',
                'date' => $document->created_at?->toDateString(),
            ]);

        return response()->json([
            'query' => $query,
            'features' => $features,
            'meals' => $meals,
            'documents' => $documents,
        ]);
    }
}
