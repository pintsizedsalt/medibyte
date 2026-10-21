<?php

namespace App\Http\Controllers;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
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

    private function daySnapshot(User $user, Carbon $date): array
    {
        $day = $date->toDateString();

        $water = (float) $user->dailyLogs()
            ->whereDate('log_date', $day)
            ->sum('water_ml');

        $sleep = $user->sleepLogs()
            ->whereDate('log_date', $day)
            ->get()
            ->sum(fn ($log) => $this->sleepHours($log));

        $steps = (float) $user->walkingLogs()
            ->whereDate('log_date', $day)
            ->sum('steps');

        return [
            'water_ml' => round($water, 1),
            'sleep_hours' => round((float) $sleep, 2),
            'steps' => round($steps),
            'has_data' => $water > 0 || $sleep > 0 || $steps > 0,
        ];
    }

    private function scoreSnapshot(User $user, array $snapshot): array
    {
        $definitions = [
            [
                'key' => 'sleep',
                'label' => 'Sleep',
                'actual' => $snapshot['sleep_hours'],
                'goal' => (float) ($user->sleep_goal_hours ?? 0),
                'display' => $this->formatHours($snapshot['sleep_hours']),
                'goal_display' => $this->formatHours($user->sleep_goal_hours),
            ],
            [
                'key' => 'hydration',
                'label' => 'Hydration',
                'actual' => $snapshot['water_ml'],
                'goal' => (float) ($user->water_goal_ml ?? 0),
                'display' => $this->formatWater($snapshot['water_ml']),
                'goal_display' => $this->formatWater($user->water_goal_ml),
            ],
            [
                'key' => 'movement',
                'label' => 'Movement',
                'actual' => $snapshot['steps'],
                'goal' => (float) ($user->walking_goal_steps ?? 0),
                'display' => $this->formatSteps($snapshot['steps']),
                'goal_display' => $this->formatSteps($user->walking_goal_steps),
            ],
        ];

        $components = [];
        foreach ($definitions as $definition) {
            if ($definition['goal'] <= 0) {
                continue;
            }

            $definition['percent'] = max(0, min(100, (int) round($definition['actual'] / $definition['goal'] * 100)));
            $components[] = $definition;
        }

        $score = count($components)
            ? (int) round(array_sum(array_column($components, 'percent')) / count($components))
            : 0;

        return [
            'score' => $score,
            'components' => $components,
        ];
    }

    private function healthMessage(int $score, array $components): array
    {
        if (! count($components)) {
            return [
                'status' => 'No goals yet',
                'summary' => 'Set your daily goals in your profile to start seeing a health score.',
            ];
        }

        $lowest = collect($components)->sortBy('percent')->first();

        if ($score >= 80) {
            $status = 'Well balanced';
            $summary = 'You are close to your daily goals across sleep, hydration, and movement.';
        } elseif ($score >= 60) {
            $status = 'On track';
            $summary = 'You are making steady progress. A little more '.strtolower($lowest['label']).' will lift your score.';
        } elseif ($score >= 40) {
            $status = 'Needs attention';
            $summary = strtolower($lowest['label']).' has the most room to improve today. Start with one small step.';
        } else {
            $status = 'Start small';
            $summary = 'Your score is built from today\'s logs. Add sleep, water, or movement to begin.';
        }

        return [
            'status' => $status,
            'summary' => ucfirst($summary),
        ];
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

    private function componentPercent(array $components, string $key): ?int
    {
        foreach ($components as $component) {
            if ($component['key'] === $key) {
                return $component['percent'];
            }
        }

        return null;
    }

    private function cycleSummary(User $user): ?array
    {
        if (! $user->show_period_tracker) {
            return null;
        }

        $cycleLength = max(20, (int) ($user->cycle_length ?: 28));
        $lastPeriod = $user->periodLogs()->orderByDesc('start_date')->first();

        if (! $lastPeriod) {
            return [
                'cycle_day' => null,
                'cycle_length' => $cycleLength,
                'last_start' => null,
                'next_start' => null,
                'days_until_next' => null,
            ];
        }

        $lastStart = $lastPeriod->start_date->copy()->startOfDay();
        $daysSince = max(0, (int) $lastStart->diffInDays(today()->startOfDay()));
        $cycleDay = ($daysSince % $cycleLength) + 1;
        $cyclesAhead = (int) floor($daysSince / $cycleLength) + 1;
        $nextStart = $lastStart->copy()->addDays($cycleLength * $cyclesAhead);

        return [
            'cycle_day' => $cycleDay,
            'cycle_length' => $cycleLength,
            'last_start' => $lastStart->toDateString(),
            'next_start' => $nextStart->toDateString(),
            'days_until_next' => (int) today()->startOfDay()->diffInDays($nextStart, false),
        ];
    }

    private function nextMedication(User $user): ?array
    {
        $now = now();
        $today = today()->toDateString();

        $medications = $user->medications()
            ->where('is_active', true)
            ->whereDate('start_date', '<=', $today)
            ->where(function ($query) use ($today) {
                $query->whereNull('end_date')->orWhereDate('end_date', '>=', $today);
            })
            ->get();

        $next = null;

        foreach ($medications as $medication) {
            foreach ($medication->times ?? [] as $time) {
                if (! preg_match('/^(\d{1,2}):(\d{2})/', (string) $time, $matches)) {
                    continue;
                }

                $doseAt = today()->copy()->setTime((int) $matches[1], (int) $matches[2]);

                if ($doseAt->lessThan($now)) {
                    $doseAt->addDay();
                }

                if (! $next || $doseAt->lessThan($next['at'])) {
                    $next = [
                        'id' => $medication->id,
                        'name' => $medication->name,
                        'dosage' => $medication->dosage,
                        'food_instruction' => $medication->food_instruction,
                        'at' => $doseAt,
                        'time' => $time,
                    ];
                }
            }
        }

        if (! $next) {
            return null;
        }

        return [
            'id' => $next['id'],
            'name' => $next['name'],
            'dosage' => $next['dosage'],
            'food_instruction' => $next['food_instruction'],
            'at' => $next['at']->toIso8601String(),
            'time' => $next['time'],
        ];
    }

    public function index(Request $request)
    {
        $user = $request->user();

        $todaySnapshot = $this->daySnapshot($user, today());
        $todayScore = $this->scoreSnapshot($user, $todaySnapshot);
        $healthMessage = $this->healthMessage($todayScore['score'], $todayScore['components']);

        $week = [];
        for ($daysAgo = 6; $daysAgo >= 0; $daysAgo--) {
            $date = today()->subDays($daysAgo);
            $snapshot = $this->daySnapshot($user, $date);
            $dayScore = $this->scoreSnapshot($user, $snapshot);

            $week[] = [
                'date' => $date->toDateString(),
                'label' => $date->format('D'),
                'score' => $dayScore['score'],
                'has_data' => $snapshot['has_data'],
            ];
        }

        $weekAverage = (int) round(array_sum(array_column($week, 'score')) / count($week));

        $cycle = $this->cycleSummary($user);
        $latestMood = $user->moodLogs()->latest('log_date')->latest('id')->first();
        $latestWeight = $user->weightLogs()->latest('log_date')->latest('id')->first();
        $latestVitals = $user->vitalSignLogs()->latest('log_date')->latest('id')->first();

        $metrics = [
            [
                'key' => 'sleep',
                'label' => 'Sleep',
                'value' => $this->formatHours($todaySnapshot['sleep_hours']),
                'detail' => 'Goal '.$this->formatHours($user->sleep_goal_hours),
                'percent' => $this->componentPercent($todayScore['components'], 'sleep'),
                'href' => '/sleep',
            ],
            [
                'key' => 'hydration',
                'label' => 'Hydration',
                'value' => $this->formatWater($todaySnapshot['water_ml']),
                'detail' => 'Goal '.$this->formatWater($user->water_goal_ml),
                'percent' => $this->componentPercent($todayScore['components'], 'hydration'),
                'href' => '/water',
            ],
            [
                'key' => 'movement',
                'label' => 'Movement',
                'value' => $this->formatSteps($todaySnapshot['steps']),
                'detail' => 'Goal '.$this->formatSteps($user->walking_goal_steps),
                'percent' => $this->componentPercent($todayScore['components'], 'movement'),
                'href' => '/walking',
            ],
        ];

        if ($cycle) {
            $cycleDetail = $cycle['cycle_day']
                ? 'Next period in '.max(0, (int) $cycle['days_until_next']).' days'
                : 'Add your cycle';

            $metrics[] = [
                'key' => 'cycle',
                'label' => 'Cycle',
                'value' => $cycle['cycle_day'] ? 'Day '.$cycle['cycle_day'] : 'Not logged',
                'detail' => $cycleDetail,
                'percent' => null,
                'href' => '/period',
            ];
        } else {
            $metrics[] = [
                'key' => 'mood',
                'label' => 'Mood',
                'value' => $latestMood ? ucfirst($latestMood->mood) : 'Not logged',
                'detail' => $latestMood
                    ? 'Intensity '.$latestMood->intensity.'/5 · '.$this->shortDate($latestMood->log_date)
                    : 'Add a mood check-in',
                'percent' => null,
                'href' => '/mood',
            ];
        }

        $metrics[] = [
            'key' => 'weight',
            'label' => 'Weight & BMI',
            'value' => $latestWeight ? number_format((float) $latestWeight->weight_kg, 1).' kg' : 'Not logged',
            'detail' => $latestWeight && $latestWeight->bmi ? 'BMI '.$latestWeight->bmi : 'Add a weight reading',
            'percent' => null,
            'href' => '/weight',
        ];

        return response()->json([
            'name' => $user->name,
            'first_name' => $user->first_name,
            'username' => $user->username,
            'onboarding_completed_at' => $user->onboarding_completed_at,
            'show_period_tracker' => $user->show_period_tracker,
            'health' => [
                'score' => $todayScore['score'],
                'status' => $healthMessage['status'],
                'summary' => $healthMessage['summary'],
                'components' => $todayScore['components'],
                'week' => $week,
                'week_average' => $weekAverage,
                'has_data' => $todaySnapshot['has_data'],
            ],
            'metrics' => $metrics,
            'cycle' => $cycle,
            'next_medication' => $this->nextMedication($user),
            'upcoming_appointments' => $user->appointments()
                ->where('status', 'scheduled')
                ->where('appointment_at', '>=', now())
                ->orderBy('appointment_at')
                ->limit(3)
                ->get(),
            'recent_symptoms' => $user->symptomLogs()
                ->whereDate('log_date', '>=', today()->subDays(30)->toDateString())
                ->orderByDesc('log_date')
                ->orderByDesc('id')
                ->limit(4)
                ->get(),
            'latest_mood' => $latestMood,
            'latest_weight' => $latestWeight,
            'latest_vitals' => $latestVitals,
        ]);
    }
}
