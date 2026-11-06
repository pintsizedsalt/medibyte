<?php

namespace Tests\Unit;

use App\Support\CyclePrediction;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

class CyclePredictionTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_no_periods_is_no_data(): void
    {
        Carbon::setTestNow('2026-10-20');

        $prediction = CyclePrediction::predict([], 28);

        $this->assertSame('no_data', $prediction['state']);
        $this->assertNull($prediction['predicted_next_start']);
    }

    public function test_one_period_is_still_learning_with_the_fallback_length(): void
    {
        Carbon::setTestNow('2026-10-20');

        $prediction = CyclePrediction::predict(['2026-10-01'], 28);

        $this->assertSame('still_learning', $prediction['state']);
        $this->assertSame(0, $prediction['based_on_cycles']);
        $this->assertSame(28, $prediction['average_cycle_length']);
        $this->assertSame('2026-10-29', $prediction['predicted_next_start']);
    }

    public function test_two_cycles_stays_still_learning(): void
    {
        Carbon::setTestNow('2026-10-20');

        $prediction = CyclePrediction::predict(['2026-08-25', '2026-09-22', '2026-10-20'], 28);

        $this->assertSame('still_learning', $prediction['state']);
        $this->assertSame(2, $prediction['based_on_cycles']);
        $this->assertSame('2026-11-17', $prediction['predicted_next_start']);
        $this->assertNotNull($prediction['predicted_range']);
    }

    public function test_three_regular_cycles_predicts_the_average(): void
    {
        Carbon::setTestNow('2026-09-26');

        $prediction = CyclePrediction::predict(['2026-07-04', '2026-08-01', '2026-08-29', '2026-09-26'], 28);

        $this->assertSame('ready', $prediction['state']);
        $this->assertSame(3, $prediction['based_on_cycles']);
        $this->assertFalse($prediction['irregular']);
        $this->assertSame('medium', $prediction['confidence']);
        $this->assertSame('2026-10-24', $prediction['predicted_next_start']);
        $this->assertNull($prediction['predicted_range']);
        $this->assertSame('2026-10-10', $prediction['ovulation_date']);
    }

    public function test_six_regular_cycles_earns_high_confidence(): void
    {
        Carbon::setTestNow('2026-12-19');

        $starts = ['2026-06-06', '2026-07-04', '2026-08-01', '2026-08-29', '2026-09-26', '2026-10-24', '2026-11-21'];

        $prediction = CyclePrediction::predict($starts, 28);

        $this->assertSame('high', $prediction['confidence']);
        $this->assertSame(6, $prediction['based_on_cycles']);
    }

    public function test_irregular_cycles_return_a_range_and_low_confidence(): void
    {
        Carbon::setTestNow('2026-10-26');

        // Gaps of 26, 30, 25 and 31 days spread wider than the regular threshold.
        $starts = ['2026-07-01', '2026-07-27', '2026-08-26', '2026-09-20', '2026-10-21'];

        $prediction = CyclePrediction::predict($starts, 28);

        $this->assertTrue($prediction['irregular']);
        $this->assertSame('low', $prediction['confidence']);
        $this->assertSame(['start' => '2026-11-15', 'end' => '2026-11-21'], $prediction['predicted_range']);
    }

    public function test_a_skipped_month_is_ignored_as_an_outlier(): void
    {
        Carbon::setTestNow('2026-09-21');

        // The 91-day gap after March is two missed months, not a cycle, so it is
        // left out of the average while the periods themselves stay in history.
        $starts = ['2026-01-05', '2026-02-02', '2026-03-02', '2026-06-01', '2026-06-29', '2026-07-27', '2026-08-24', '2026-09-21'];

        $prediction = CyclePrediction::predict($starts, 28);

        $this->assertSame(6, $prediction['based_on_cycles']);
        $this->assertSame(28, $prediction['average_cycle_length']);
        $this->assertFalse($prediction['irregular']);
    }

    public function test_a_late_period_is_reported(): void
    {
        Carbon::setTestNow('2026-10-27');

        $prediction = CyclePrediction::predict(['2026-07-04', '2026-08-01', '2026-08-29', '2026-09-26'], 28);

        $this->assertTrue($prediction['is_late']);
        $this->assertSame(3, $prediction['days_late']);
        $this->assertStringContainsString('3 days late', $prediction['message']);
    }
}
