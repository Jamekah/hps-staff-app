<?php

namespace App\Observers;

use App\Models\StaffLeave;
use App\Services\ClinicBooking;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Clinic appointments carry a stored `has_clash` flag, set when they were
 * booked. Leave is recorded afterwards, so every change to a leave record can
 * make those flags wrong in either direction.
 *
 * Recomputing here rather than in the Livewire component means the invariant
 * holds wherever leave is written from — the page, a seeder, or a future
 * import — and an edit covers both the old and the new range.
 */
class StaffLeaveObserver
{
    public function __construct(protected ClinicBooking $booking)
    {
    }

    public function created(StaffLeave $leave): void
    {
        $this->recompute($leave);
    }

    public function updated(StaffLeave $leave): void
    {
        $this->recompute($leave, [
            $leave->getOriginal('first_day'),
            $leave->getOriginal('return_date'),
        ]);
    }

    public function deleted(StaffLeave $leave): void
    {
        $this->recompute($leave);
    }

    /**
     * @param  array{0: mixed, 1: mixed}|null  $previousRange  Pre-edit dates, so
     *                                                         appointments the leave
     *                                                         no longer covers are freed.
     */
    protected function recompute(StaffLeave $leave, ?array $previousRange = null): void
    {
        $from = $leave->startsAt();
        $to = $leave->endsAt();

        if ($previousRange !== null) {
            [$previousFirst, $previousReturn] = $previousRange;

            if ($previousFirst) {
                $from = $this->earliest($from, Carbon::parse($previousFirst)->startOfDay());
            }

            if ($previousReturn) {
                $to = $this->latest($to, Carbon::parse($previousReturn)->startOfDay());
            }
        }

        $this->booking->recomputeClashesFor($leave->user_id, $from, $to);
    }

    protected function earliest(CarbonInterface $a, CarbonInterface $b): CarbonInterface
    {
        return $a->lte($b) ? $a : $b;
    }

    protected function latest(CarbonInterface $a, CarbonInterface $b): CarbonInterface
    {
        return $a->gte($b) ? $a : $b;
    }
}
