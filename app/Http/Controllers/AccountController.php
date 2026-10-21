<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AccountController extends Controller
{
    public function export(Request $request)
    {
        $user = $request->user()->load([
            'dailyLogs',
            'sleepLogs',
            'walkingLogs',
            'mealLogs',
            'weightLogs',
            'moodLogs',
            'periodLogs',
            'symptomLogs',
            'vitalSignLogs',
            'medications',
            'appointments',
            'documents',
        ]);

        return response()->json([
            'exported_at' => now()->toIso8601String(),
            'account' => [
                'name' => $user->name,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'username' => $user->username,
                'email' => $user->email,
                'consented_at' => $user->consented_at,
                'created_at' => $user->created_at,
            ],
            'profile' => [
                'sex' => $user->sex,
                'period_status' => $user->period_status,
                'weight_goal' => $user->weight_goal,
                'water_goal_ml' => $user->water_goal_ml,
                'sleep_goal_hours' => $user->sleep_goal_hours,
                'walking_goal_steps' => $user->walking_goal_steps,
                'calorie_goal' => $user->calorie_goal,
                'cycle_length' => $user->cycle_length,
                'reminders_enabled' => $user->reminders_enabled,
                'onboarding_completed_at' => $user->onboarding_completed_at,
                'show_period_tracker' => $user->show_period_tracker,
            ],
            'daily_logs' => $user->dailyLogs,
            'sleep_logs' => $user->sleepLogs,
            'walking_logs' => $user->walkingLogs,
            'meal_logs' => $user->mealLogs,
            'weight_logs' => $user->weightLogs,
            'mood_logs' => $user->moodLogs,
            'period_logs' => $user->periodLogs,
            'symptom_logs' => $user->symptomLogs,
            'vital_sign_logs' => $user->vitalSignLogs,
            'medications' => $user->medications,
            'appointments' => $user->appointments,
            'documents' => $user->documents->map(fn ($document) => [
                'title' => $document->title,
                'type' => $document->type,
                'original_name' => $document->original_name,
                'mime_type' => $document->mime_type,
                'size' => $document->size,
                'notes' => $document->notes,
                'created_at' => $document->created_at,
            ]),
            'note' => 'Document files are stored privately and are not embedded in this export. Download them from the Documents page before deleting your account.',
        ])->header(
            'Content-Disposition',
            'attachment; filename="medibyte-export-'.now()->format('Y-m-d').'.json"'
        );
    }

    public function destroy(Request $request)
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        $user = $request->user();

        if (! Hash::check($request->input('password'), $user->password)) {
            throw ValidationException::withMessages([
                'password' => 'The password is incorrect.',
            ]);
        }

        $documentPaths = $user->documents->pluck('path');

        DB::transaction(function () use ($user) {
            $user->notifications()->delete();
            $user->tokens()->delete();
            $user->delete();
        });

        foreach ($documentPaths as $path) {
            Storage::disk('local')->delete($path);
        }

        return response()->json(['message' => 'Account deleted']);
    }
}
