<?php

namespace App\Support;

use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The weekly PDF a user takes to a clinic.
 *
 * It covers the seven days ending today — the same window the dashboard and the
 * record pages show — and is laid out for someone reading it on paper: who the
 * report is about first, then the daily trend, then one small table per kind of
 * record, and a plain statement that everything here was self-reported. A kind
 * with nothing logged in the week is left out entirely, so the report carries
 * what happened rather than a list of things the account holder did not do.
 */
class WeeklyReport
{
    private const DISCLAIMER = 'Self-reported by the account holder through MediByte. This is a personal log, not a medical record or a diagnosis.';

    private Carbon $from;

    private Carbon $to;

    private Collection $water;

    private Collection $sleep;

    private Collection $walking;

    private Collection $meals;

    private Collection $moods;

    private Collection $symptoms;

    private Collection $vitals;

    private Collection $weights;

    private Collection $periods;

    private Collection $medications;

    private Collection $appointments;

    private Collection $documents;

    public function __construct(private readonly User $user)
    {
        $this->to = now()->startOfDay();
        $this->from = $this->to->copy()->subDays(6);
    }

    public static function fileName(): string
    {
        return 'medibyte-weekly-summary-'.now()->format('Y-m-d').'.pdf';
    }

    public function pdf(): string
    {
        $pdf = new Pdf(
            'MediByte Weekly Health Summary',
            'Self-reported health record for the seven days ending '.now()->format('j F Y'),
            $this->user->name
        );

        $this->gather();

        $this->masthead($pdf);
        $this->atAGlance($pdf);
        $this->dayByDay($pdf);
        $this->vitalSigns($pdf);
        $this->weight($pdf);
        $this->symptoms($pdf);
        $this->medications($pdf);
        $this->appointments($pdf);
        $this->mood($pdf);
        $this->cycle($pdf);
        $this->documents($pdf);
        $this->closing($pdf);

        return $pdf->output(self::DISCLAIMER);
    }

    private function gather(): void
    {
        $days = [$this->from->toDateString(), $this->to->toDateString()];
        $timestamps = [$this->from->toDateTimeString(), $this->to->copy()->endOfDay()->toDateTimeString()];

        $this->water = $this->user->dailyLogs()->whereBetween('log_date', $days)->get();
        $this->sleep = $this->user->sleepLogs()->whereBetween('log_date', $days)->get();
        $this->walking = $this->user->walkingLogs()->whereBetween('log_date', $days)->get();
        $this->meals = $this->user->mealLogs()->whereBetween('log_date', $days)->orderBy('log_date')->orderBy('meal_time')->get();
        $this->moods = $this->user->moodLogs()->whereBetween('log_date', $days)->orderBy('log_date')->orderBy('id')->get();
        $this->symptoms = $this->user->symptomLogs()->whereBetween('log_date', $days)->orderBy('log_date')->orderByDesc('severity')->get();
        $this->vitals = $this->user->vitalSignLogs()->whereBetween('log_date', $days)->orderBy('log_date')->orderBy('id')->get();
        $this->weights = $this->user->weightLogs()->whereBetween('log_date', $days)->orderBy('log_date')->get();
        $this->periods = $this->user->periodLogs()
            ->where('start_date', '<=', $days[1])
            ->where(fn ($query) => $query->whereNull('end_date')->orWhere('end_date', '>=', $days[0]))
            ->orderBy('start_date')
            ->get();
        $this->medications = $this->user->medications()
            ->where(fn ($query) => $query->where('is_active', true)->orWhereBetween('end_date', $days))
            ->orderBy('name')
            ->get();
        $this->appointments = $this->user->appointments()->whereBetween('appointment_at', $timestamps)->orderBy('appointment_at')->get();
        $this->documents = $this->user->documents()->whereBetween('created_at', $timestamps)->orderBy('created_at')->get();
    }

    private function masthead(Pdf $pdf): void
    {
        $pdf->text('MediByte Weekly Health Summary', 16, Pdf::BOLD);
        $pdf->text(
            $this->from->format('D j M Y').' to '.$this->to->format('D j M Y').' · seven days ending today',
            9.5,
            Pdf::ITALIC
        );

        $pdf->space(6);

        $pdf->keyValues([
            ['Patient', $this->user->name ?: 'Unnamed'],
            ['Prepared', now()->format('j M Y, H:i')],
            ['Account', (string) $this->user->email],
            ['Sex', $this->user->sex ? ucfirst($this->user->sex) : 'Not recorded'],
            ['Daily goals', $this->goalLine()],
            ['Logged days', $this->coverageLine()],
        ]);
    }

    private function atAGlance(Pdf $pdf): void
    {
        $sleep = $this->average($this->sleepByDay());
        $water = $this->average($this->waterByDay());
        $steps = $this->average($this->walkingByDay('steps'));
        $calories = $this->average($this->caloriesByDay());

        $pdf->sectionHeading('At a glance');

        $pdf->summaryCards([
            ['label' => 'Sleep per day', 'value' => $sleep === null ? 'Not logged' : $this->hours($sleep), 'tone' => '#7b5fd9'],
            ['label' => 'Water per day', 'value' => $water === null ? 'Not logged' : $this->water($water), 'tone' => '#3f8fd2'],
            ['label' => 'Steps per day', 'value' => $steps === null ? 'Not logged' : $this->steps($steps), 'tone' => '#3fa97a'],
            ['label' => 'Calories per day', 'value' => $calories === null ? 'No meal logs' : number_format($calories).' kcal', 'tone' => '#d98a3f'],
            ['label' => 'Symptom entries', 'value' => (string) $this->symptoms->count(), 'tone' => '#c95c5c'],
            ['label' => 'Mood entries', 'value' => (string) $this->moods->count(), 'tone' => '#c9a227'],
            ['label' => 'Vital readings', 'value' => (string) $this->vitals->count(), 'tone' => '#4f6fe0'],
            ['label' => 'Active medicines', 'value' => (string) $this->medications->where('is_active', true)->count(), 'tone' => '#5f7a99'],
        ]);

        $pdf->text($this->goalPercentLine(), 8.5, Pdf::ITALIC);
    }

    private function dayByDay(Pdf $pdf): void
    {
        $pdf->sectionHeading('The week, day by day');

        $columns = [
            ['label' => 'Day', 'width' => 74],
            ['label' => 'Sleep', 'width' => 64, 'align' => 'right'],
            ['label' => 'Water', 'width' => 64, 'align' => 'right'],
            ['label' => 'Movement', 'width' => 78, 'align' => 'right'],
            ['label' => 'Meals', 'width' => 60, 'align' => 'right'],
            ['label' => 'Mood', 'width' => 94],
            ['label' => 'Symptoms', 'width' => 65],
        ];

        $sleep = $this->sleepByDay();
        $water = $this->waterByDay();
        $steps = $this->walkingByDay('steps');
        $minutes = $this->walkingByDay('minutes');
        $calories = $this->caloriesByDay();
        $moods = $this->moods->groupBy(fn ($log) => $this->dayOf($log->log_date));
        $symptoms = $this->symptoms->groupBy(fn ($log) => $this->dayOf($log->log_date));

        $rows = [];

        foreach ($this->days() as $day) {
            $key = $day->toDateString();
            $dayMoods = $moods->get($key, collect());
            $daySymptoms = $symptoms->get($key, collect());

            $rows[] = [
                $day->format('D j M'),
                $sleep->has($key) ? $this->hours($sleep->get($key)) : '—',
                $water->has($key) ? $this->water($water->get($key)) : '—',
                $steps->has($key) ? $this->steps($steps->get($key)) : ($minutes->has($key) ? ((int) $minutes->get($key)).' min' : '—'),
                $calories->has($key) ? number_format((int) $calories->get($key)).' kcal' : '—',
                $dayMoods->isEmpty()
                    ? '—'
                    : $dayMoods->map(fn ($log) => $this->moodLabel($log).' ·'.$log->intensity)->implode(', '),
                $daySymptoms->isEmpty()
                    ? '—'
                    : $daySymptoms->take(2)->pluck('symptom')->implode(', ')
                        .($daySymptoms->count() > 2 ? ' +'.($daySymptoms->count() - 2) : ''),
            ];
        }

        $pdf->table($columns, $rows);
    }

    private function vitalSigns(Pdf $pdf): void
    {
        if ($this->vitals->isEmpty()) {
            return;
        }

        $pdf->sectionHeading('Vital signs');

        $columns = [
            ['label' => 'When', 'width' => 100],
            ['label' => 'Blood pressure', 'width' => 96, 'align' => 'right'],
            ['label' => 'Pulse', 'width' => 84, 'align' => 'right'],
            ['label' => 'Temperature', 'width' => 108, 'align' => 'right'],
            ['label' => 'Oxygen', 'width' => 106, 'align' => 'right'],
        ];

        $rows = $this->vitals->map(fn ($log) => [
            $this->shortDate($log->log_date).($log->created_at ? ', '.$log->created_at->format('H:i') : ''),
            $log->systolic && $log->diastolic ? $log->systolic.'/'.$log->diastolic.' mmHg' : '—',
            $log->heart_rate ? $log->heart_rate.' bpm' : '—',
            $log->temperature_c ? rtrim(rtrim(number_format((float) $log->temperature_c, 1), '0'), '.').' °C' : '—',
            $log->oxygen_saturation ? $log->oxygen_saturation.'%' : '—',
        ])->all();

        $pdf->table($columns, $rows);
    }

    private function weight(Pdf $pdf): void
    {
        $outsideWindow = $this->weights->isEmpty();
        $logs = $outsideWindow
            ? $this->user->weightLogs()->latest('log_date')->limit(1)->get()
            : $this->weights;

        if ($logs->isEmpty()) {
            return;
        }

        $pdf->sectionHeading('Weight and BMI');

        $columns = [
            ['label' => 'Day', 'width' => 96],
            ['label' => 'Weight', 'width' => 100, 'align' => 'right'],
            ['label' => 'Height', 'width' => 100, 'align' => 'right'],
            ['label' => 'BMI', 'width' => 78, 'align' => 'right'],
            ['label' => 'BMI category', 'width' => 125],
        ];

        $rows = $logs->map(fn ($log) => [
            $this->shortDate($log->log_date),
            $log->weight_kg ? rtrim(rtrim(number_format((float) $log->weight_kg, 1), '0'), '.').' kg' : '—',
            $log->height_cm ? rtrim(rtrim(number_format((float) $log->height_cm, 1), '0'), '.').' cm' : '—',
            $log->bmi ? rtrim(rtrim(number_format((float) $log->bmi, 1), '0'), '.') : '—',
            $this->bmiCategory($log->bmi),
        ])->all();

        $pdf->table($columns, $rows);

        if ($outsideWindow) {
            $pdf->text('No weight logged in this period — the reading above is the most recent one on file.', 8.5, Pdf::ITALIC);
        }
    }

    private function symptoms(Pdf $pdf): void
    {
        if ($this->symptoms->isEmpty()) {
            return;
        }

        $pdf->sectionHeading('Symptoms');

        $columns = [
            ['label' => 'Day', 'width' => 74],
            ['label' => 'Symptom', 'width' => 124],
            ['label' => 'Illness', 'width' => 108],
            ['label' => 'Severity', 'width' => 62, 'align' => 'right'],
            ['label' => 'Notes', 'width' => 131],
        ];

        $rows = $this->symptoms->map(fn ($log) => [
            $this->shortDate($log->log_date),
            (string) $log->symptom,
            $log->illness_name ?: '—',
            $log->severity.'/5',
            $log->notes ? Str::limit((string) $log->notes, 120) : '—',
        ])->all();

        $pdf->table($columns, $rows);
    }

    private function medications(Pdf $pdf): void
    {
        if ($this->medications->isEmpty()) {
            return;
        }

        $pdf->sectionHeading('Medications');

        $columns = [
            ['label' => 'Medicine', 'width' => 118],
            ['label' => 'Dose', 'width' => 80],
            ['label' => 'Scheduled times', 'width' => 104],
            ['label' => 'Taking', 'width' => 92],
            ['label' => 'Status', 'width' => 105],
        ];

        $rows = $this->medications->map(fn ($medication) => [
            $medication->name,
            $medication->dosage ?: '—',
            empty($medication->times) ? '—' : implode(', ', $medication->times),
            $this->foodInstruction($medication->food_instruction),
            $this->medicationStatus($medication),
        ])->all();

        $pdf->table($columns, $rows);
    }

    private function appointments(Pdf $pdf): void
    {
        $next = $this->user->appointments()
            ->where('appointment_at', '>', $this->to->copy()->endOfDay())
            ->where('status', 'scheduled')
            ->orderBy('appointment_at')
            ->first();

        if ($this->appointments->isEmpty() && ! $next) {
            return;
        }

        $pdf->sectionHeading('Appointments');

        if ($this->appointments->isNotEmpty()) {
            $columns = [
                ['label' => 'When', 'width' => 96],
                ['label' => 'Who', 'width' => 112],
                ['label' => 'Where', 'width' => 118],
                ['label' => 'Reason', 'width' => 110],
                ['label' => 'Status', 'width' => 63],
            ];

            $rows = $this->appointments->map(fn ($appointment) => [
                $appointment->appointment_at->format('D j M, H:i'),
                $appointment->doctor_name.($appointment->specialty ? ' · '.Str::limit((string) $appointment->specialty, 26, '') : ''),
                $appointment->hospital ?: '—',
                $appointment->reason ? Str::limit((string) $appointment->reason, 80) : '—',
                ucfirst((string) $appointment->status),
            ])->all();

            $pdf->table($columns, $rows);
        }

        if ($next) {
            $pdf->pair('Next scheduled', $this->appointmentLine($next));
        }
    }

    private function appointmentLine($appointment): string
    {
        return $appointment->appointment_at->format('D j M Y, H:i').' · '.($appointment->doctor_name ?: 'Doctor not named');
    }

    private function mood(Pdf $pdf): void
    {
        if ($this->moods->isEmpty()) {
            return;
        }

        $pdf->sectionHeading('Mood');

        $columns = [
            ['label' => 'Day', 'width' => 74],
            ['label' => 'Mood', 'width' => 112],
            ['label' => 'Intensity', 'width' => 60, 'align' => 'right'],
            ['label' => 'What affected it', 'width' => 140],
            ['label' => 'Event', 'width' => 113],
        ];

        $rows = $this->moods->map(fn ($log) => [
            $this->shortDate($log->log_date),
            $this->moodLabel($log),
            $log->intensity.'/5',
            empty($log->impacts) ? '—' : implode(', ', $log->impacts),
            $log->has_big_event ? ($log->event_note ? Str::limit((string) $log->event_note, 60) : 'Yes') : '—',
        ])->all();

        $pdf->table($columns, $rows);

        $reflections = $this->moods->filter(fn ($log) => filled($log->reflection));

        if ($reflections->isNotEmpty()) {
            $pdf->space(2);
            $pdf->text('Diary reflections', 9, Pdf::BOLD);

            foreach ($reflections as $log) {
                $pdf->text($this->shortDate($log->log_date).': '.Str::limit((string) $log->reflection, 240), 9, Pdf::REGULAR, null, 'left', null, 12);
            }
        }
    }

    private function cycle(Pdf $pdf): void
    {
        if (! $this->user->show_period_tracker || $this->periods->isEmpty()) {
            return;
        }

        $pdf->sectionHeading('Cycle');

        $pdf->keyValues([
            ['Cycle length', ($this->user->cycle_length ?: 28).' days'],
            ['Period status', $this->periodStatus()],
        ]);

        $columns = [
            ['label' => 'Start', 'width' => 96],
            ['label' => 'End', 'width' => 96],
            ['label' => 'Flow', 'width' => 84],
            ['label' => 'Notes', 'width' => 223],
        ];

        $rows = $this->periods->map(fn ($log) => [
            $log->start_date ? $this->shortDate($log->start_date) : '—',
            $log->end_date ? $this->shortDate($log->end_date) : 'Ongoing',
            $log->flow ? ucfirst($log->flow) : '—',
            $log->notes ? Str::limit((string) $log->notes, 160) : '—',
        ])->all();

        $pdf->table($columns, $rows);
    }

    private function documents(Pdf $pdf): void
    {
        if ($this->documents->isEmpty()) {
            return;
        }

        $pdf->sectionHeading('Documents');

        $columns = [
            ['label' => 'Added', 'width' => 84],
            ['label' => 'Title', 'width' => 170],
            ['label' => 'Kind', 'width' => 120],
            ['label' => 'File', 'width' => 125],
        ];

        $rows = $this->documents->map(fn ($document) => [
            $document->created_at->format('j M'),
            $document->title ?: Str::limit((string) $document->original_name, 40),
            Str::headline((string) $document->type),
            Str::limit((string) $document->original_name, 30),
        ])->all();

        $pdf->table($columns, $rows);

        $pdf->text('Files are stored privately in the app and are not attached to this PDF.', 8.5, Pdf::ITALIC);
    }

    private function closing(Pdf $pdf): void
    {
        $pdf->space(6);
        $pdf->band(self::DISCLAIMER.' Gaps are shown as — and mean nothing was logged, not a zero reading.');

        $pdf->text('Generated by MediByte for '.$this->user->email.' on '.now()->format('j F Y \a\t H:i').' (Asia/Manila).', 8, Pdf::ITALIC);
    }

    /** @return array<int, Carbon> */
    private function days(): array
    {
        $days = [];

        for ($offset = 6; $offset >= 0; $offset--) {
            $days[] = $this->to->copy()->subDays($offset);
        }

        return $days;
    }

    private function sleepByDay(): Collection
    {
        return $this->sleep
            ->groupBy(fn ($log) => $this->dayOf($log->log_date))
            ->map(fn ($logs) => round($logs->sum(fn ($log) => $this->nightHours($log)), 2))
            ->filter(fn ($hours) => $hours > 0);
    }

    private function waterByDay(): Collection
    {
        return $this->water
            ->groupBy(fn ($log) => $this->dayOf($log->log_date))
            ->map(fn ($logs) => (float) $logs->sum('water_ml'))
            ->filter(fn ($millilitres) => $millilitres > 0);
    }

    private function walkingByDay(string $column): Collection
    {
        return $this->walking
            ->groupBy(fn ($log) => $this->dayOf($log->log_date))
            ->map(fn ($logs) => (float) $logs->sum($column))
            ->filter(fn ($value) => $value > 0);
    }

    private function caloriesByDay(): Collection
    {
        return $this->meals
            ->filter(fn ($meal) => $meal->calories !== null)
            ->groupBy(fn ($meal) => $this->dayOf($meal->log_date))
            ->map(fn ($meals) => (float) $meals->sum('calories'))
            ->filter(fn ($calories) => $calories > 0);
    }

    private function average(Collection $byDay): ?float
    {
        return $byDay->isEmpty() ? null : round((float) $byDay->avg(), 2);
    }

    private function goalLine(): string
    {
        $goals = array_filter([
            $this->user->sleep_goal_hours ? 'Sleep '.$this->hours((float) $this->user->sleep_goal_hours) : null,
            $this->user->water_goal_ml ? 'Water '.$this->water((float) $this->user->water_goal_ml) : null,
            $this->user->walking_goal_steps ? $this->steps((float) $this->user->walking_goal_steps) : null,
            $this->user->calorie_goal ? number_format((float) $this->user->calorie_goal).' kcal' : null,
        ]);

        return $goals === [] ? 'None set' : implode(' · ', $goals);
    }

    private function coverageLine(): string
    {
        $parts = [
            'sleep '.$this->sleepByDay()->count().'/7',
            'water '.$this->waterByDay()->count().'/7',
            'movement '.$this->walkingByDay('steps')->count().'/7',
            'meals '.$this->caloriesByDay()->count().'/7',
        ];

        return implode(' · ', $parts);
    }

    private function goalPercentLine(): string
    {
        $comparisons = [
            'sleep' => [$this->average($this->sleepByDay()), (float) ($this->user->sleep_goal_hours ?? 0)],
            'water' => [$this->average($this->waterByDay()), (float) ($this->user->water_goal_ml ?? 0)],
            'movement' => [$this->average($this->walkingByDay('steps')), (float) ($this->user->walking_goal_steps ?? 0)],
            'meals' => [$this->average($this->caloriesByDay()), (float) ($this->user->calorie_goal ?? 0)],
        ];

        $parts = [];

        foreach ($comparisons as $label => [$actual, $goal]) {
            if ($actual === null || $goal <= 0) {
                continue;
            }

            $parts[] = $label.' '.min(999, (int) round($actual / $goal * 100)).'%';
        }

        return $parts === []
            ? 'Averages count the days with a log only. Set daily goals in the app to see how the week compares.'
            : 'Average against the daily goals: '.implode(' · ', $parts).'. Averages count the days with a log only.';
    }

    private function nightHours($log): float
    {
        if ($log->bedtime && $log->wake_time) {
            try {
                $bed = Carbon::parse($log->bedtime);
                $wake = Carbon::parse($log->wake_time);

                if ($wake->lessThanOrEqualTo($bed)) {
                    $wake->addDay();
                }

                return round($bed->diffInMinutes($wake) / 60, 2);
            } catch (\Throwable $exception) {
                // Fall back to the stored hours when the times are incomplete.
            }
        }

        return round((float) ($log->hours ?? 0), 2);
    }

    private function moodLabel($log): string
    {
        return Str::ucfirst(str_replace('_', ' ', (string) $log->mood));
    }

    private function bmiCategory($bmi): string
    {
        if (! $bmi) {
            return '—';
        }

        return match (true) {
            $bmi < 18.5 => 'Underweight',
            $bmi < 25 => 'Healthy range',
            $bmi < 30 => 'Overweight',
            default => 'Obesity range',
        };
    }

    private function foodInstruction(?string $value): string
    {
        return match ($value) {
            'before_meal' => 'Before meals',
            'after_meal' => 'After meals',
            'with_meal' => 'With meals',
            'anytime' => 'Anytime',
            default => '—',
        };
    }

    private function medicationStatus($medication): string
    {
        if (! $medication->is_active) {
            return 'Stopped'.($medication->end_date ? ' '.$this->shortDate($medication->end_date) : '');
        }

        return $medication->end_date ? 'Active until '.$this->shortDate($medication->end_date) : 'Active';
    }

    private function periodStatus(): string
    {
        return match ($this->user->period_status) {
            'active' => 'Periods are current',
            'not_yet' => 'No periods yet',
            'menopause' => 'Menopause',
            'prefer_not_to_track' => 'Not tracked',
            default => 'Not recorded',
        };
    }

    private function hours($hours): string
    {
        $totalMinutes = (int) round(((float) $hours) * 60);

        if ($totalMinutes <= 0) {
            return '0h';
        }

        $hoursPart = intdiv($totalMinutes, 60);
        $minutesPart = $totalMinutes % 60;

        if ($hoursPart && $minutesPart) {
            return $hoursPart.'h '.$minutesPart.'m';
        }

        return $hoursPart ? $hoursPart.'h' : $minutesPart.'m';
    }

    private function water($millilitres): string
    {
        $millilitres = (float) $millilitres;

        if ($millilitres >= 1000) {
            return rtrim(rtrim(number_format($millilitres / 1000, 1), '0'), '.').' L';
        }

        return (int) round($millilitres).' ml';
    }

    private function steps($steps): string
    {
        return number_format((float) $steps).' steps';
    }

    private function shortDate($date): string
    {
        try {
            return Carbon::parse($date)->format('D j M');
        } catch (\Throwable $exception) {
            return '';
        }
    }

    private function dayOf($value): string
    {
        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value)->toDateString();
        }

        return substr((string) $value, 0, 10);
    }
}
