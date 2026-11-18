<?php

namespace App\Services;

use Carbon\CarbonInterface;

/**
 * Working-day arithmetic for leave.
 *
 * Mon–Fri only. PNG public holidays are deliberately out of scope for Phase 6,
 * but this is the single place they would plug in: fill `holidays()` with a
 * list of Y-m-d strings (from config, a table, or an API) and every count in
 * the app picks it up without further change.
 */
class WorkingDays
{
    /**
     * Working days in the half-open window [$from, $to) — the same shape leave
     * itself uses, so "first day away" to "back at work on" counts correctly.
     */
    public static function between(CarbonInterface $from, CarbonInterface $to): int
    {
        $day = $from->copy()->startOfDay();
        $end = $to->copy()->startOfDay();
        $count = 0;

        while ($day->lt($end)) {
            if (static::isWorkingDay($day)) {
                $count++;
            }

            $day->addDay();
        }

        return $count;
    }

    public static function isWorkingDay(CarbonInterface $date): bool
    {
        if ($date->isWeekend()) {
            return false;
        }

        return ! in_array($date->toDateString(), static::holidays(), true);
    }

    /**
     * Public holidays excluded from working-day counts.
     *
     * Empty by design: PNG's holiday calendar is out of scope for Phase 6.
     *
     * @return array<int, string>  Y-m-d dates.
     */
    public static function holidays(): array
    {
        return config('app.public_holidays', []);
    }
}
