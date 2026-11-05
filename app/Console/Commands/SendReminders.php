<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\HealthReminder;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SendReminders extends Command
{
    protected $signature = 'medibyte:send-reminders';

    protected $description = 'Send in-app reminders for due medications, upcoming appointments, and symptom check-ins';

    public function handle(): int
    {
        $now = now();
        $since = $this->lastSweepAt($now);
        $sent = 0;

        User::query()
            ->where('reminders_enabled', true)
            ->chunkById(100, function ($users) use ($now, $since, &$sent) {
                foreach ($users as $user) {
                    $sent += $this->sweepUser($user, $now, $since);
                }
            });

        Cache::put('medibyte:reminder-sweep', $now->timestamp, now()->addDay());

        $this->info("Sent {$sent} reminder(s).");

        return self::SUCCESS;
    }

    public function sweepUser(User $user, ?Carbon $now = null): int
    {
        $now ??= now();
        $since = $this->lastSweepAt($now);

        return $this->sendAppointmentReminders($user, $now)
            + $this->sendMedicationReminders($user, $now, $since)
            + $this->sendSymptomCheckIn($user, $now);
    }

    private function lastSweepAt(Carbon $now): Carbon
    {
        $timestamp = Cache::get('medibyte:reminder-sweep');

        return $timestamp
            ? Carbon::createFromTimestamp((int) $timestamp, $now->timezone)
            : $now->copy()->subHours(6);
    }

    private function sendAppointmentReminders(User $user, Carbon $now): int
    {
        $sent = 0;

        $appointments = $user->appointments()
            ->where('status', 'scheduled')
            ->whereBetween('appointment_at', [$now, $now->copy()->addDay()])
            ->orderBy('appointment_at')
            ->get();

        foreach ($appointments as $appointment) {
            $key = 'appointment:'.$appointment->id.':'.$appointment->appointment_at->format('Y-m-d H:i');

            if ($this->alreadySent($user, $key)) {
                continue;
            }

            $user->notify(new HealthReminder([
                'dedupe_key' => $key,
                'kind' => 'appointment',
                'title' => 'Upcoming appointment',
                'body' => 'You have an appointment with '.$appointment->doctor_name.' on '
                    .$appointment->appointment_at->format('M j, Y g:i A').'.',
                'url' => '/appointments',
                'doctor' => $appointment->doctor_name,
                'specialty' => $appointment->specialty,
                'hospital' => $appointment->hospital,
                'reason' => $appointment->reason,
                'at' => $appointment->appointment_at->toIso8601String(),
            ]));

            $sent++;
        }

        return $sent;
    }

    private function sendMedicationReminders(User $user, Carbon $now, Carbon $since): int
    {
        $sent = 0;
        $today = $now->toDateString();

        $medications = $user->medications()
            ->where('is_active', true)
            ->whereDate('start_date', '<=', $today)
            ->where(function ($query) use ($today) {
                $query->whereNull('end_date')->orWhereDate('end_date', '>=', $today);
            })
            ->get();

        foreach ($medications as $medication) {
            foreach ($medication->times ?? [] as $time) {
                [$hour, $minute] = array_pad(explode(':', $time), 2, '0');
                $dueAt = $now->copy()->startOfDay()->setTime((int) $hour, (int) $minute);

                // Skip doses that are still ahead, or that belong to a sweep already covered.
                if ($dueAt->greaterThan($now) || $dueAt->lessThanOrEqualTo($since)) {
                    continue;
                }

                $key = 'medication:'.$medication->id.':'.$today.':'.$time;

                if ($this->alreadySent($user, $key)) {
                    continue;
                }

                $user->notify(new HealthReminder([
                    'dedupe_key' => $key,
                    'kind' => 'medication',
                    'title' => 'Medication reminder',
                    'body' => 'Time for '.$medication->name
                        .($medication->dosage ? ' ('.$medication->dosage.')' : '')
                        .' at '.$time.'.',
                    'url' => '/medications',
                    'medication_id' => $medication->id,
                    'name' => $medication->name,
                    'dosage' => $medication->dosage,
                    'time' => $time,
                    'food_instruction' => $medication->food_instruction ?: 'anytime',
                    'schedule' => $medication->times ?? [],
                    'notes' => $medication->notes,
                ]));

                $sent++;
            }
        }

        return $sent;
    }

    private function sendSymptomCheckIn(User $user, Carbon $now): int
    {
        $hour = (int) $now->format('G');

        if ($hour < 8 || $hour > 20) {
            return 0;
        }

        $today = $now->toDateString();
        $key = 'symptom:'.$today;

        if ($this->alreadySent($user, $key)) {
            return 0;
        }

        $hasRecentSymptoms = $user->symptomLogs()
            ->whereDate('log_date', '>=', $now->copy()->subDays(7)->toDateString())
            ->whereDate('log_date', '<', $today)
            ->exists();

        if (! $hasRecentSymptoms) {
            return 0;
        }

        $loggedToday = $user->symptomLogs()
            ->whereDate('log_date', $today)
            ->exists();

        if ($loggedToday) {
            return 0;
        }

        $user->notify(new HealthReminder([
            'dedupe_key' => $key,
            'kind' => 'symptom',
            'title' => 'Symptom check-in',
            'body' => 'How are you feeling today? Log your symptoms to keep your health record up to date.',
            'url' => '/symptoms',
        ]));

        return 1;
    }

    private function alreadySent(User $user, string $key): bool
    {
        return $user->notifications()
            ->latest()
            ->limit(200)
            ->get()
            ->contains(fn ($notification) => ($notification->data['dedupe_key'] ?? null) === $key);
    }
}
