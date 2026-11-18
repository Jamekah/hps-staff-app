<?php

namespace Tests\Unit;

use App\Services\WorkingDays;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * Working-day arithmetic for leave. The window is half-open — [first day away,
 * back at work on) — so the return date itself is never counted.
 */
class WorkingDaysTest extends TestCase
{
    public function test_a_single_weekday_counts_as_one(): void
    {
        // Monday 1 Dec 2025 → back Tuesday.
        $this->assertSame(1, WorkingDays::between(
            Carbon::parse('2025-12-01'),
            Carbon::parse('2025-12-02'),
        ));
    }

    public function test_a_full_week_skips_the_weekend(): void
    {
        // Monday 1 Dec → back the following Monday: 5 working, 7 calendar.
        $this->assertSame(5, WorkingDays::between(
            Carbon::parse('2025-12-01'),
            Carbon::parse('2025-12-08'),
        ));
    }

    public function test_a_fortnight_counts_ten(): void
    {
        $this->assertSame(10, WorkingDays::between(
            Carbon::parse('2025-12-01'),
            Carbon::parse('2025-12-15'),
        ));
    }

    public function test_a_weekend_only_absence_counts_nothing(): void
    {
        // Saturday 6 Dec → back Monday 8 Dec.
        $this->assertSame(0, WorkingDays::between(
            Carbon::parse('2025-12-06'),
            Carbon::parse('2025-12-08'),
        ));
    }

    public function test_leave_starting_on_a_friday_counts_the_friday_only(): void
    {
        // Friday 5 Dec → back Monday 8 Dec.
        $this->assertSame(1, WorkingDays::between(
            Carbon::parse('2025-12-05'),
            Carbon::parse('2025-12-08'),
        ));
    }

    public function test_an_empty_window_counts_nothing(): void
    {
        $this->assertSame(0, WorkingDays::between(
            Carbon::parse('2025-12-01'),
            Carbon::parse('2025-12-01'),
        ));
    }

    public function test_weekends_are_not_working_days(): void
    {
        $this->assertTrue(WorkingDays::isWorkingDay(Carbon::parse('2025-12-05')));
        $this->assertFalse(WorkingDays::isWorkingDay(Carbon::parse('2025-12-06')));
        $this->assertFalse(WorkingDays::isWorkingDay(Carbon::parse('2025-12-07')));
    }
}
