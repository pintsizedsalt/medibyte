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
                $medicationReminders[] = [
                    'medication_id' => $med->id,
                    'name' => $med->name,
                    'dosage' => $med->dosage,
                    'time' => $time,
                    'food_instruction' => $med->food_instruction,
                    'already_passed' => $time < $now->format('H:i'),
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
            'medications' => collect($medicationReminders)->sortBy('time')->values(),
            'appointments' => $appointments,
        ]);
    }
}