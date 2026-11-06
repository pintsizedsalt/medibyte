<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\TextField;
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

        TextField::normalize($request, ['first_name' => 'name', 'last_name' => 'name']);

        $data = $request->validate([
            'first_name' => array_merge(['sometimes'], TextField::rules('name', 'required', 80)),
            'last_name' => array_merge(['sometimes'], TextField::rules('name', 'required', 80)),
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
            'weight_goal' => 'nullable|numeric|min:1|max:500',
            'water_goal_ml' => 'integer|min:500|max:20000',
            'sleep_goal_hours' => 'numeric|min:1|max:16',
            'walking_goal_steps' => 'integer|min:0|max:100000',
            'calorie_goal' => 'nullable|integer|min:500|max:10000',
            'cycle_length' => 'integer|min:20|max:45',
            'reminders_enabled' => 'boolean',
            'appointment_reminders_enabled' => 'sometimes|boolean',
            // A lead time is either one of the offered choices or a custom amount typed
            // in by hand, so the bound is the feature's window rather than a whitelist.
            'appointment_lead_minutes' => ['sometimes', 'array', 'max:'.(count(User::APPOINTMENT_LEADS) + User::MAX_CUSTOM_LEADS)],
            'appointment_lead_minutes.*' => ['integer', 'between:0,'.User::CUSTOM_LEAD_CEILINGS['appointment']],
            'appointment_day_notice' => 'sometimes|boolean',
            'appointment_repeat_minutes' => ['sometimes', 'nullable', 'integer', Rule::in(array_keys(User::REPEAT_INTERVALS))],
            'medication_reminders_enabled' => 'sometimes|boolean',
            'medication_lead_minutes' => ['sometimes', 'array', 'max:'.(count(User::MEDICATION_LEADS) + User::MAX_CUSTOM_LEADS)],
            'medication_lead_minutes.*' => ['integer', 'between:0,'.User::CUSTOM_LEAD_CEILINGS['medication']],
            'medication_repeat_minutes' => ['sometimes', 'nullable', 'integer', Rule::in(array_keys(User::REPEAT_INTERVALS))],
            'cycle_reminders_enabled' => 'sometimes|boolean',
            'cycle_lead_days' => ['sometimes', 'integer', Rule::in(array_keys(User::CYCLE_LEAD_DAYS))],
            'cycle_reminder_time' => 'sometimes|date_format:H:i',
            'cycle_fertile_reminder' => 'sometimes|boolean',
            'cycle_ovulation_reminder' => 'sometimes|boolean',
            'cycle_pms_reminder' => 'sometimes|boolean',
            'cycle_late_reminder' => 'sometimes|boolean',
            'cycle_daily_log_reminder' => 'sometimes|boolean',
            'cycle_daily_log_time' => 'sometimes|nullable|date_format:H:i',
            'cycle_pill_reminder' => 'sometimes|boolean',
            'cycle_pill_time' => 'sometimes|nullable|date_format:H:i',
            'cycle_discreet' => 'sometimes|boolean',
            'cycle_paused' => 'sometimes|boolean',
            'cycle_pause_reason' => ['sometimes', 'nullable', 'string', 'max:60'],
        ], TextField::messages(['first_name' => 'name', 'last_name' => 'name']));

        $data = $this->orderedLeads($data);

        if (($data['sex'] ?? null) === 'male') {
            $data['period_status'] = null;
        }

        if (array_key_exists('cycle_paused', $data) && ! $data['cycle_paused']) {
            $data['cycle_pause_reason'] = null;
        }

        if (array_key_exists('first_name', $data) || array_key_exists('last_name', $data)) {
            $firstName = $data['first_name'] ?? $user->first_name;
            $lastName = $data['last_name'] ?? $user->last_name;
            $data['name'] = trim($firstName.' '.$lastName);
        }

        $user->fill($data)->save();

        return response()->json($user->refresh(), 201);
    }

    /**
     * Lead times are stored once each, nearest notice first, so the choices a
     * person unticks really disappear instead of leaving a duplicate behind.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function orderedLeads(array $data): array
    {
        foreach (['appointment_lead_minutes', 'medication_lead_minutes'] as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            $leads = array_values(array_unique(array_map('intval', $data[$field])));
            sort($leads);

            $data[$field] = $leads;
        }

        return $data;
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
