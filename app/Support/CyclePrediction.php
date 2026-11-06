<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Turns a person's logged period starts into a prediction of the next one.
 *
 * The engine reads the gaps between consecutive starts rather than every cycle
 * in history, so one skipped month does not drag the average for a year. Gaps
 * outside a biologically plausible window are treated as outliers and ignored,
 * though the periods themselves stay in the record. Ovulation is counted back
 * from the expected next period using a fixed luteal phase, and the fertile
 * window is the handful of days that lead up to it.
 */
final class CyclePrediction
{
    /** Ovulation sits this many days before the next period. */
    public const LUTEAL_PHASE_DAYS = 14;

    /** The fertile window opens this many days before ovulation. */
    public const FERTILE_WINDOW_DAYS = 5;

    /** A gap outside this range is a skipped month, not a cycle. */
    public const MIN_PLAUSIBLE_CYCLE = 15;
    public const MAX_PLAUSIBLE_CYCLE = 60;

    /** Fewer than this many usable gaps means the app is still learning. */
    public const MIN_CYCLES_FOR_READING = 3;

    /** The average is drawn from the most recent cycles, never all of them. */
    public const MAX_CYCLES_FOR_AVERAGE = 6;

    /** A spread wider than this between the recent cycles reads as irregular. */
    public const IRREGULAR_SPREAD_DAYS = 3;

    /**
     * @param  array<int, string|\DateTimeInterface>  $starts  period start dates, any order
     * @return array<string, mixed>
     */
    public static function predict(array $starts, int $fallbackCycleLength = 28, ?Carbon $today = null): array
    {
        $today = ($today ?? now())->copy()->startOfDay();

        $dates = array_values(array_map(
            fn ($start) => Carbon::parse($start instanceof \DateTimeInterface ? $start->format('Y-m-d') : $start)->startOfDay(),
            $starts
        ));

        usort($dates, fn (Carbon $a, Carbon $b) => $a->lessThan($b) ? -1 : 1);

        $last = $dates === [] ? null : end($dates);

        $gaps = [];
        for ($i = 1; $i < count($dates); $i++) {
            $gaps[] = (int) $dates[$i - 1]->diffInDays($dates[$i]);
        }

        $plausible = array_values(array_filter(
            $gaps,
            fn (int $gap) => $gap >= self::MIN_PLAUSIBLE_CYCLE && $gap <= self::MAX_PLAUSIBLE_CYCLE
        ));

        $basedOn = count($plausible);

        if ($last === null) {
            return self::blank($today, $fallbackCycleLength);
        }

        $cycleDay = max(1, (int) $last->diffInDays($today) + 1);

        // One logged period is not a cycle yet, so there is nothing to average.
        if ($basedOn === 0) {
            return self::build([
                'state' => 'still_learning',
                'last_period_start' => $last,
                'cycle_day' => $cycleDay,
                'average_cycle_length' => $fallbackCycleLength,
                'based_on_cycles' => 0,
                'confidence' => null,
                'predicted_next_start' => $last->copy()->addDays($fallbackCycleLength),
                'predicted_range' => null,
                'irregular' => false,
                'late' => false,
                'days_late' => 0,
                'message' => 'Still learning your cycle — keep logging periods to sharpen the estimate.',
            ], $today);
        }

        $recent = array_slice($plausible, -self::MAX_CYCLES_FOR_AVERAGE);
        $average = (int) round(array_sum($recent) / count($recent));
        $spread = max($recent) - min($recent);

        $learning = $basedOn < self::MIN_CYCLES_FOR_READING;
        $irregular = ! $learning && $spread > self::IRREGULAR_SPREAD_DAYS;

        $next = $last->copy()->addDays($average);

        $range = null;
        if ($learning) {
            // A wider window while the app has too little to go on.
            $range = [$last->copy()->addDays($average - self::IRREGULAR_SPREAD_DAYS), $last->copy()->addDays($average + self::IRREGULAR_SPREAD_DAYS)];
        } elseif ($irregular) {
            $range = [$last->copy()->addDays(min($recent)), $last->copy()->addDays(max($recent))];
        }

        $confidence = $learning ? null : ($irregular ? 'low' : ($basedOn >= self::MAX_CYCLES_FOR_AVERAGE ? 'high' : 'medium'));

        $anchor = $range !== null ? $range[1] : $next;
        $lateBy = (int) $anchor->diffInDays($today, false);
        $late = ! $learning && $lateBy > 0;

        return self::build([
            'state' => $learning ? 'still_learning' : 'ready',
            'last_period_start' => $last,
            'cycle_day' => $cycleDay,
            'average_cycle_length' => $average,
            'based_on_cycles' => $basedOn,
            'confidence' => $confidence,
            'predicted_next_start' => $next,
            'predicted_range' => $range,
            'irregular' => $irregular,
            'late' => $late,
            'days_late' => $late ? $lateBy : 0,
            'message' => self::message($learning, $irregular, $late, $lateBy, $next, $range),
        ], $today);
    }

    /**
     * @param  array<string, mixed>  $prediction
     * @return array<string, mixed>
     */
    private static function build(array $prediction, Carbon $today): array
    {
        $last = $prediction['last_period_start'];
        $next = $prediction['predicted_next_start'];
        $range = $prediction['predicted_range'];

        $ovulation = $next instanceof Carbon ? $next->copy()->subDays(self::LUTEAL_PHASE_DAYS) : null;

        return [
            'state' => $prediction['state'],
            'last_period_start' => $last?->toDateString(),
            'average_cycle_length' => $prediction['average_cycle_length'],
            'based_on_cycles' => $prediction['based_on_cycles'],
            'cycle_day' => $prediction['cycle_day'],
            'confidence' => $prediction['confidence'],
            'irregular' => $prediction['irregular'],
            'predicted_next_start' => $next?->toDateString(),
            'predicted_range' => $range === null
                ? null
                : ['start' => $range[0]->toDateString(), 'end' => $range[1]->toDateString()],
            'ovulation_date' => $ovulation?->toDateString(),
            'fertile_start' => $ovulation?->copy()->subDays(self::FERTILE_WINDOW_DAYS)->toDateString(),
            'fertile_end' => $ovulation?->toDateString(),
            'days_until_next' => $next ? (int) $today->diffInDays($next, false) : null,
            'is_late' => $prediction['late'],
            'days_late' => $prediction['days_late'],
            'message' => $prediction['message'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function blank(Carbon $today, int $fallbackCycleLength): array
    {
        return [
            'state' => 'no_data',
            'last_period_start' => null,
            'average_cycle_length' => $fallbackCycleLength,
            'based_on_cycles' => 0,
            'cycle_day' => null,
            'confidence' => null,
            'irregular' => false,
            'predicted_next_start' => null,
            'predicted_range' => null,
            'ovulation_date' => null,
            'fertile_start' => null,
            'fertile_end' => null,
            'days_until_next' => null,
            'is_late' => false,
            'days_late' => 0,
            'message' => 'Log your first period to start seeing predictions.',
        ];
    }

    /**
     * @param  array<int, Carbon>|null  $range
     */
    private static function message(bool $learning, bool $irregular, bool $late, int $lateBy, Carbon $next, ?array $range): string
    {
        if ($late) {
            return 'Your period is '.$lateBy.' day'.($lateBy === 1 ? '' : 's').' late. Cycles vary — take it easy and consider logging a pregnancy test.';
        }

        if ($learning) {
            return 'Still learning your cycle — the estimate will sharpen as you log more periods.';
        }

        if ($irregular) {
            return 'Your cycles vary, so the next period is expected '.self::rangeLabel($range).'.';
        }

        return 'Your period is expected '.self::dayLabel($next).'.';
    }

    private static function rangeLabel(array $range): string
    {
        return self::dayLabel($range[0]).' to '.self::dayLabel($range[1]);
    }

    private static function dayLabel(Carbon $day): string
    {
        return 'on '.$day->format('M j');
    }
}
