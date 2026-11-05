<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Models\Medication;
use App\Models\User;
use App\Notifications\HealthReminder;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SendReminders extends Command
{
    protected $signature = 'medibyte:send-reminders';

    protected $description = 'Send in-app reminders for due medications, upcoming appointments, and symptom check-ins';

    /**
     * A dose nobody has opened a reminder for stops being nudged this long after it
     * was due, so a forgotten 8am tablet does not go on asking until midnight.
     */
    private const MEDICATION_LATE_MINUTES = 180;

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

    public function sweepUser(User $user, ?Carbon $now = null, ?Carbon $since = null): int
    {
        if (! $user->reminders_enabled) {
            return 0;
        }

        $now ??= now();
        $since ??= $this->lastSweepAt($now);

        return $this->sendAppointmentReminders($user, $now, $since)
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

    private function sendAppointmentReminders(User $user, Carbon $now, Carbon $since): int
    {
        if (! $user->appointment_reminders_enabled) {
            return 0;
        }

        $sent = 0;

        // Matches the seven-day window the reminders feed already shows, so a visit
        // booked for next week is announced as soon as it is saved when that is what
        // the person asked for, and again at every lead time they chose.
        $appointments = $user->appointments()
            ->where('status', 'scheduled')
            ->whereBetween('appointment_at', [$now, $now->copy()->addDays(7)])
            ->orderBy('appointment_at')
            ->get();

        foreach ($appointments as $appointment) {
            $notice = $this->appointmentNotice($appointment, $user, $now);

            if ($notice && $this->remind($user, $notice['key'], $notice['payload'])) {
                $sent++;
            }

            if ($this->dueDayNotice($appointment, $user, $now)) {
                $key = $this->appointmentAnchor($appointment).':today';

                if ($this->remind($user, $key, $this->appointmentPayload($appointment, null, $now))) {
                    $sent++;
                }
            }

            if ($notice && $this->repeatNotice($user, $notice, $now, $since, $user->appointmentRepeat(), $appointment->appointment_at)) {
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * The notice an appointment is due for, which is the nearest lead time that has
     * passed and none further back: an app that was shut for an afternoon should
     * catch the person up on the visit, not deliver three stale reminders for it.
     * The model hands the leads over shortest-first, so the first one already past
     * the appointment is the nearest and the rest are further behind it.
     *
     * @return array{key: string, at: Carbon, payload: array<string, mixed>}|null
     */
    private function appointmentNotice(Appointment $appointment, User $user, Carbon $now): ?array
    {
        foreach ($user->appointmentLeads() as $lead) {
            $fireAt = $appointment->appointment_at->copy()->subMinutes($lead);

            if ($fireAt->greaterThan($now)) {
                continue;
            }

            return [
                'key' => $this->appointmentAnchor($appointment).':lead'.$lead,
                'at' => $fireAt,
                'payload' => $this->appointmentPayload($appointment, $lead, $now),
            ];
        }

        return null;
    }

    private function dueDayNotice(Appointment $appointment, User $user, Carbon $now): bool
    {
        return $user->appointment_day_notice
            && $appointment->appointment_at->toDateString() === $now->toDateString()
            && (int) $now->format('G') >= User::DAY_NOTICE_HOUR;
    }

    private function sendMedicationReminders(User $user, Carbon $now, Carbon $since): int
    {
        if (! $user->medication_reminders_enabled) {
            return 0;
        }

        $sent = 0;
        $today = $now->toDateString();

        $medications = $user->medications()
            ->where('is_active', true)
            ->whereDate('start_date', '<=', $today)
            ->where(function ($query) use ($today) {
                $query->whereNull('end_date')->orWhereDate('end_date', '>=', $today);
            })
            ->get();

        $leads = $user->medicationLeads();

        foreach ($medications as $medication) {
            foreach ($medication->times ?? [] as $time) {
                $dueAt = $this->doseTime($time, $now);

                if ($dueAt === null) {
                    continue;
                }

                $notice = $this->medicationNotice($medication, $time, $dueAt, $leads, $now);

                // A dose whose moment passed before the last sweep is not announced —
                // the person is told about today's schedule, not a backlog. It still
                // anchors any repeat, which stays inside the grace period below.
                if ($notice && $notice['at']->greaterThan($since) && $this->remind($user, $notice['key'], $notice['payload'])) {
                    $sent++;
                }

                $endsAt = $dueAt->copy()->addMinutes(self::MEDICATION_LATE_MINUTES);

                if ($notice && $this->repeatNotice($user, $notice, $now, $since, $user->medicationRepeat(), $endsAt)) {
                    $sent++;
                }
            }
        }

        return $sent;
    }

    /**
     * Today's moment for this dose, or null when the saved time cannot be read, so
     * every reminder stays anchored to the schedule the person recorded.
     */
    private function doseTime(string $time, Carbon $now): ?Carbon
    {
        [$hour, $minute] = array_pad(explode(':', $time), 2, '0');

        if (! is_numeric($hour) || ! is_numeric($minute)) {
            return null;
        }

        return $now->copy()->startOfDay()->setTime((int) $hour, (int) $minute);
    }

    /**
     * The dose this schedule is due for, which is the nearest lead time that has
     * passed and none further back, so a person who opens the app late is nudged for
     * the dose in front of them rather than for every offset they configured. The
     * leads arrive shortest-first, so the first one already past is the nearest.
     *
     * @param  array<int, int>  $leads
     * @return array{key: string, at: Carbon, payload: array<string, mixed>}|null
     */
    private function medicationNotice(Medication $medication, string $time, Carbon $dueAt, array $leads, Carbon $now): ?array
    {
        $anchor = 'medication:'.$medication->id.':'.$dueAt->toDateString().':'.$time;

        foreach ($leads as $lead) {
            $fireAt = $dueAt->copy()->subMinutes($lead);

            if ($fireAt->greaterThan($now)) {
                continue;
            }

            return [
                'key' => $anchor.':lead'.$lead,
                'at' => $fireAt,
                'payload' => $this->medicationPayload($medication, $time, $lead, $dueAt, $now),
            ];
        }

        return null;
    }

    /**
     * One follow-up per sweep for a reminder that is still unopened, spaced by the
     * interval the person chose and ending at `$ceiling` — the appointment itself,
     * or the end of the grace period on a dose. Opening or throwing away the earlier
     * reminder answers it, which is what makes a repeat safe to offer at all.
     *
     * @param  array{key: string, at: Carbon, payload: array<string, mixed>}  $notice
     */
    private function repeatNotice(User $user, array $notice, Carbon $now, Carbon $since, ?int $repeat, Carbon $ceiling): bool
    {
        if (! $repeat || $now->greaterThanOrEqualTo($ceiling) || $this->acknowledged($user, $notice['key'])) {
            return false;
        }

        $elapsed = (int) floor(($now->timestamp - $notice['at']->timestamp) / 60);
        $slot = intdiv($elapsed, $repeat);

        if ($slot < 1) {
            return false;
        }

        $dueAt = $notice['at']->copy()->addMinutes($slot * $repeat);

        if ($dueAt->lessThanOrEqualTo($since)) {
            return false;
        }

        return $this->remind($user, $notice['key'].':every'.$repeat.':'.$slot, $notice['payload'] + ['anchor_key' => $notice['key']]);
    }

    private function sendSymptomCheckIn(User $user, Carbon $now): int
    {
        $hour = (int) $now->format('G');

        if ($hour < 8 || $hour > 20) {
            return 0;
        }

        $today = $now->toDateString();

        $hasRecentSymptoms = $user->symptomLogs()
            ->whereDate('log_date', '>=', $now->copy()->subDays(7)->toDateString())
            ->whereDate('log_date', '<', $today)
            ->exists();

        if (! $hasRecentSymptoms) {
            return 0;
        }

        if ($user->symptomLogs()->whereDate('log_date', $today)->exists()) {
            return 0;
        }

        return $this->remind($user, 'symptom:'.$today, [
            'kind' => 'symptom',
            'title' => 'Symptom check-in',
            'body' => 'How are you feeling today? Log your symptoms to keep your health record up to date.',
            'url' => '/symptoms',
        ]) ? 1 : 0;
    }

    /**
     * The notice the inbox opens. A null lead is the one sent on the day itself.
     *
     * @return array<string, mixed>
     */
    private function appointmentPayload(Appointment $appointment, ?int $lead, Carbon $now): array
    {
        $when = $appointment->appointment_at;
        $countdown = $this->countdown($when, $now);

        $with = trim(($appointment->doctor_name ?? '').' '.($appointment->hospital ? 'at '.$appointment->hospital : ''));

        $body = ($with === '' ? '' : $with.' ')
            .'on '.$when->format('D j M Y, g:i A')
            .($countdown === '' ? '' : ' '.$countdown)
            .($appointment->reason ? ' · '.$appointment->reason : '');

        return [
            'kind' => 'appointment',
            'title' => $this->appointmentTitle($lead, $countdown),
            'body' => $body,
            'url' => '/appointments',
            'doctor' => $appointment->doctor_name,
            'specialty' => $appointment->specialty,
            'hospital' => $appointment->hospital,
            'reason' => $appointment->reason,
            'at' => $when->toIso8601String(),
            'lead_minutes' => $lead,
        ];
    }

    private function appointmentTitle(?int $lead, string $countdown): string
    {
        if ($lead === null) {
            return 'Appointment today';
        }

        if ($lead >= 10080) {
            return 'Appointment booked';
        }

        return $countdown === ''
            ? 'Appointment is due'
            : 'Appointment '.$countdown;
    }

    /**
     * The card the inbox opens. A dose whose moment has already gone is announced as
     * due now, because that is the truth a catch-up reminder has to tell.
     *
     * @return array<string, mixed>
     */
    private function medicationPayload(Medication $medication, string $time, int $lead, Carbon $dueAt, Carbon $now): array
    {
        $dose = $medication->name.($medication->dosage ? ' ('.$medication->dosage.')' : '');
        $isOverdue = $dueAt->lessThanOrEqualTo($now);

        return [
            'kind' => 'medication',
            'title' => $isOverdue ? 'Medication reminder' : 'Medication due soon',
            'body' => $isOverdue
                ? 'Time for '.$dose.' at '.$time.'.'
                : $dose.' is due at '.$time.', '.$this->leadAhead($lead).'.',
            'url' => '/medications',
            'medication_id' => $medication->id,
            'name' => $medication->name,
            'dosage' => $medication->dosage,
            'time' => $time,
            'lead_minutes' => $lead,
            'food_instruction' => $medication->food_instruction ?: 'anytime',
            'schedule' => $medication->times ?? [],
            'notes' => $medication->notes,
        ];
    }

    private function leadAhead(int $minutes): string
    {
        if ($minutes < 60) {
            return 'in '.$minutes.' minute'.($minutes === 1 ? '' : 's');
        }

        $hours = intdiv($minutes, 60);

        return 'in '.$hours.' hour'.($hours === 1 ? '' : 's');
    }

    /** The gap between now and the appointment, left blank once it has arrived. */
    private function countdown(Carbon $when, Carbon $now): string
    {
        $minutes = (int) floor(($when->timestamp - $now->timestamp) / 60);

        if ($minutes < 1) {
            return '';
        }

        if ($minutes < 60) {
            return 'in '.$minutes.' minute'.($minutes === 1 ? '' : 's');
        }

        if ($minutes < 1440) {
            $hours = (int) round($minutes / 60);

            return 'in '.$hours.' hour'.($hours === 1 ? '' : 's');
        }

        $days = (int) round($minutes / 1440);

        return 'in '.$days.' day'.($days === 1 ? '' : 's');
    }

    private function appointmentAnchor(Appointment $appointment): string
    {
        return 'appointment:'.$appointment->id.':'.$appointment->appointment_at->format('Y-m-d H:i');
    }

    /**
     * Deliver one reminder, once. The claim is remembered beside the notification so
     * a reminder thrown away from the inbox stays thrown away.
     *
     * @param  array<string, mixed>  $payload
     */
    private function remind(User $user, string $key, array $payload): bool
    {
        if ($this->alreadySent($user, $key)) {
            return false;
        }

        $user->notify(new HealthReminder($payload + ['dedupe_key' => $key]));

        Cache::put(HealthReminder::claimMarker($key), true, HealthReminder::markerTtl());

        return true;
    }

    private function alreadySent(User $user, string $key): bool
    {
        return Cache::has(HealthReminder::claimMarker($key))
            || $user->notifications()->where('data->dedupe_key', $key)->exists();
    }

    private function acknowledged(User $user, string $key): bool
    {
        if (Cache::has(HealthReminder::dismissalMarker($key))) {
            return true;
        }

        return $user->notifications()
            ->where('data->dedupe_key', $key)
            ->whereNotNull('read_at')
            ->exists();
    }
}
