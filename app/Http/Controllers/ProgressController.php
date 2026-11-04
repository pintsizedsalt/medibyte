<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;

class ProgressController extends Controller
{
    private function metric($actual, $goal, $judge = true)
    {
        $actual = (float) $actual;

        if (! $goal) {
            return ['goal' => null, 'actual' => $actual, 'status' => 'no_goal'];
        }

        $goal = (float) $goal;

        $result = [
            'goal' => $goal,
            'actual' => $actual,
            'percent' => max(0, min(100, (int) round($actual / $goal * 100))),
            'remaining' => max(0, $goal - $actual),
        ];

        if ($judge) {
            $result['status'] = $actual >= $goal ? 'goal_met' : 'below_goal';
        }

        return $result;
    }

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

    public function index(Request $request)
    {
        $request->validate(['date' => 'nullable|date']);

        $user = $request->user();
        $date = $request->query('date', now()->toDateString());

        $water = $user->dailyLogs()->whereDate('log_date', $date)->sum('water_ml');
        $sleep = $user->sleepLogs()->whereDate('log_date', $date)->get()->sum(fn ($log) => $this->sleepHours($log));
        $steps = $user->walkingLogs()->whereDate('log_date', $date)->sum('steps');
        $calories = $user->mealLogs()->whereDate('log_date', $date)->sum('calories');

        $latestWeight = $user->weightLogs()->latest('log_date')->first();

        return response()->json([
            'date' => $date,
            'water_ml' => $this->metric($water, $user->water_goal_ml),
            'sleep_hours' => $this->metric($sleep, $user->sleep_goal_hours),
            'walking_steps' => $this->metric($steps, $user->walking_goal_steps),
            'calories' => $this->metric($calories, $user->calorie_goal, false),
            'weight' => [
                'current_kg' => $latestWeight?->weight_kg,
                'bmi' => $latestWeight?->bmi,
                'goal_kg' => $user->weight_goal,
            ],
        ]);
    }
}
