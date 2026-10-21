<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ReminderController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $now = now();
        $today = $now->toDateString();

        if (! $user->reminders_enabled) {
            return response()->json([
                'date' => $today,
                'enabled' => false,
                'medications' => [],
                'appointments' => [],
            ]);
        }

        $medications = $user->medications()
            ->where('is_active', true)
            ->whereDate('start_date', '<=', $today)
            ->where(function ($q) use ($today) {
                $q->whereNull('end_date')->orWhereDate('end_date', '>=', $today);
            })
            ->get();

        $medicationReminders = [];

        foreach ($medications as $med) {
            foreach ($med->times ?? [] as $time) {
                // Convert time to minutes for reliable comparison (handles "9:30" vs "10:00")
                $timeParts = explode(':', $time);
                $timeMinutes = count($timeParts) === 2
                    ? ($timeParts[0] * 60) + $timeParts[1]
                    : (int)$time;

                $nowMinutes = (int)$now->format('Hi'); // HHMM as integer

                $medicationReminders[] = [
                    'medication_id' => $med->id,
                    'name' => $med->name,
                    'dosage' => $med->dosage,
                    'time' => $time,
                    'food_instruction' => $med->food_instruction,
                    'already_passed' => $timeMinutes < $nowMinutes,
                ];
            }
        }

        $appointments = $user->appointments()
            ->where('status', 'scheduled')
            ->whereBetween('appointment_at', [$now, $now->copy()->addDays(7)])
            ->orderBy('appointment_at')
            ->get();

        return response()->json([
            'date' => $today,
            'enabled' => true,
            'medications' => collect($medicationReminders)->sortBy('time')->values(),
            'appointments' => $appointments,
        ]);
    }
}