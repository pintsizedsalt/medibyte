<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ProgressController extends Controller
{
    private function metric($actual, $goal, $judge = true)
    {
        $actual = (float) $actual;

        if (!$goal) {
            return ['goal' => null, 'actual' => $actual, 'status' => 'no_goal'];
        }

        $goal = (float) $goal;

        $result = [
            'goal' => $goal,
            'actual' => $actual,
            'percent' => round($actual / $goal * 100),
            'remaining' => max(0, $goal - $actual),
        ];

        if ($judge) {
            $result['status'] = $actual >= $goal ? 'goal_met' : 'below_goal';
        }

        return $result;
    }

    public function index(Request $request)
    {
        $request->validate(['date' => 'nullable|date']);

        $user = $request->user();
        $date = $request->query('date', now()->toDateString());
        $profile = $user->profile;

        $water = $user->dailyLogs()->whereDate('log_date', $date)->sum('water_ml');
        $sleep = $user->sleepLogs()->whereDate('log_date', $date)->sum('hours');
        $steps = $user->walkingLogs()->whereDate('log_date', $date)->sum('steps');
        $calories = $user->mealLogs()->whereDate('log_date', $date)->sum('calories');

        $latestWeight = $user->weightLogs()->latest('log_date')->first();

        return response()->json([
            'date' => $date,
            'water_ml' => $this->metric($water, $profile?->water_goal_ml),
            'sleep_hours' => $this->metric($sleep, $profile?->sleep_goal_hours),
            'walking_steps' => $this->metric($steps, $profile?->walking_goal_steps),
            'calories' => $this->metric($calories, $profile?->calorie_goal, false),
            'weight' => [
                'current_kg' => $latestWeight?->weight_kg,
                'bmi' => $latestWeight?->bmi,
                'goal_kg' => $profile?->weight_goal,
            ],
        ]);
    }
}