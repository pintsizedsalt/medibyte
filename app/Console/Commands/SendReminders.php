<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\HealthReminder;
use Illuminate\Console\Command;

class SendReminders extends Command
{
    protected $signature = 'medibyte:send-reminders';

    protected $description = 'Send in-app reminders for due medications and upcoming appointments';

    public function handle(): int
    {
        $now = now();
        $today = $now->toDateString();
        $windowStart = $now->copy()->format('H:i');
        $windowEnd = $now->copy()->addMinutes(15)->format('H:i');
        $sent = 0;

        User::query()
            ->where('reminders_enabled', true)
            ->chunkById(100, function ($users) use ($now, $today, $windowStart, $windowEnd, &$sent) {
                foreach ($users as $user) {
                    $sent += $this->sendAppointmentReminders($user, $now);
                    $sent += $this->sendMedicationReminders($user, $today, $windowStart, $windowEnd);
                }
            });

        $this->info("Sent {$sent} reminder(s).");

        return self::SUCCESS;
    }

    private function sendAppointmentReminders(User $user, $now): int
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
            ]));

            $sent++;
        }

        return $sent;
    }

    private function sendMedicationReminders(User $user, string $today, string $windowStart, string $windowEnd): int
    {
        $sent = 0;

        $medications = $user->medications()
            ->where('is_active', true)
            ->whereDate('start_date', '<=', $today)
            ->where(function ($query) use ($today) {
                $query->whereNull('end_date')->orWhereDate('end_date', '>=', $today);
            })
            ->get();

        foreach ($medications as $medication) {
            foreach ($medication->times ?? [] as $time) {
                if (! $this->timeIsInWindow($time, $windowStart, $windowEnd)) {
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
                ]));

                $sent++;
            }
        }

        return $sent;
    }

    private function timeIsInWindow(string $time, string $start, string $end): bool
    {
        if ($end >= $start) {
            return $time >= $start && $time <= $end;
        }

        // The 15-minute window crosses midnight.
        return $time >= $start || $time <= $end;
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
