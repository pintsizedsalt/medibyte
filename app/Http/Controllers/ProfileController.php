<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
        public function show(Request $request)
    {
        return response()->json($request->user()->profile);
    }

        public function store(Request $request)
    {
        $data = $request->validate([
            'sex' => 'sometimes|in:female,male',
            'period_status' => 'required_if:sex,female|nullable|in:active,not_yet,menopause',
            'weight_goal' => 'nullable|numeric',
            'water_goal_ml' => 'integer|min:500',
            'sleep_goal_hours' => 'numeric|min:1|max:16',
            'walking_goal_steps' => 'integer|min:0',
            'calorie_goal' => 'nullable|integer|min:500',
            'cycle_length' => 'integer|min:20|max:45',
            'reminders_enabled' => 'boolean',
        ]);

        if (($data['sex'] ?? null) === 'male') {
            $data['period_status'] = null;
        }

        $profile = Profile::updateOrCreate(
            ['user_id' => $request->user()->id],
            $data
        );

        return response()->json($profile, 201);
    }
}
