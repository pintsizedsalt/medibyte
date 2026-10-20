<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $profile = $user->profile;

        $progress = app(ProgressController::class)->index($request)->getData(true);
        $reminders = app(ReminderController::class)->index($request)->getData(true);

        $period = null;
        if ($profile && $profile->show_period_tracker) {
            $period = app(PeriodLogController::class)->predict($request)->getData(true);
        }

        return response()->json([
            'name' => $user->name,
            'profile' => $profile,
            'latest_mood' => $user->moodLogs()->latest('log_date')->first(),
            'progress' => $progress,
            'reminders' => $reminders,
            'period_prediction' => $period,
        ]);
    }
}