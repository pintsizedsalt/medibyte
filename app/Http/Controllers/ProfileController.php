<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        return response()->json($request->user());
    }

    public function store(Request $request)
    {
        $user = $request->user();

        if ($request->has('username')) {
            $request->merge([
                'username' => strtolower(trim((string) $request->input('username'))),
            ]);
        }

        $data = $request->validate([
            'first_name' => 'sometimes|required|string|max:80',
            'last_name' => 'sometimes|required|string|max:80',
            'username' => [
                'sometimes',
                'required',
                'string',
                'min:3',
                'max:30',
                'regex:/^[A-Za-z0-9_]+$/',
                Rule::unique('users', 'username')->ignore($user->id),
            ],
            'email' => [
                'sometimes',
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'sex' => 'sometimes|in:female,male',
            'period_status' => 'nullable|in:active,not_yet,menopause,prefer_not_to_track',
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

        if (array_key_exists('first_name', $data) || array_key_exists('last_name', $data)) {
            $firstName = $data['first_name'] ?? $user->first_name;
            $lastName = $data['last_name'] ?? $user->last_name;
            $data['name'] = trim($firstName.' '.$lastName);
        }

        $user->fill($data)->save();

        return response()->json($user->refresh(), 201);
    }

    public function complete(Request $request)
    {
        $user = $request->user();

        if (! $user->sex) {
            throw ValidationException::withMessages([
                'profile' => 'Please finish your profile before continuing.',
            ]);
        }

        if ($user->sex === 'female' && ! $user->period_status) {
            throw ValidationException::withMessages([
                'period_status' => 'Please choose how you want to handle cycle tracking.',
            ]);
        }

        $user->forceFill(['onboarding_completed_at' => now()])->save();

        return response()->json($user->refresh());
    }
}
