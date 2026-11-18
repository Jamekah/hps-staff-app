<?php

namespace App\Services;

use App\Enums\LeaveType;
use App\Models\StaffLeave;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * The single source of truth for "how much leave has this person used?".
 *
 * Every figure in the app — the Staff tab's column, the detail sheet's totals,
 * the stats panel and the warning while recording leave — comes through here,
 * so they can never disagree.
 *
 * The rules, all locked in scoping (see PHASE-6.1-BRIEF.md):
 *  - the allowance year is the calendar year, and leave spanning New Year
 *    counts only its own days in each year;
 *  - counting is in working days (Mon–Fri) over the half-open window
 *    [first_day, return_date), matching leave itself;
 *  - future leave already recorded counts as used, so nobody over-books;
 *  - cancelled leave never counts;
 *  - there is no carry-over, and no pro-rata for mid-year starters.
 */
class LeaveAllowance
{
    /** Working days used in a year: taken so far plus already booked. */
    public function usedDays(User|int $user, int $year, ?LeaveType $type = null, ?int $ignoreLeaveId = null): int
    {
        return $this->split($user, $year, $type, $ignoreLeaveId)['used'];
    }

    /** The part of this year's leave that is already behind us. */
    public function takenDays(User|int $user, int $year, ?LeaveType $type = null, ?int $ignoreLeaveId = null): int
    {
        return $this->split($user, $year, $type, $ignoreLeaveId)['taken'];
    }

    /** The part still ahead — recorded, but not yet served. */
    public function bookedDays(User|int $user, int $year, ?LeaveType $type = null, ?int $ignoreLeaveId = null): int
    {
        return $this->split($user, $year, $type, $ignoreLeaveId)['booked'];
    }

    /**
     * Days left in the allowance. Null when the type has no limit; negative
     * when the person is over, which is allowed — going over warns, it never
     * blocks.
     */
    public function remaining(User|int $user, int $year, LeaveType $type, ?int $ignoreLeaveId = null): ?int
    {
        $limit = $type->allowance();

        if ($limit === null) {
            return null;
        }

        return $limit - $this->usedDays($user, $year, $type, $ignoreLeaveId);
    }

    public function limitFor(LeaveType $type): ?int
    {
        return $type->allowance();
    }

    /**
     * Everything the stats panel needs, for any number of staff, in a fixed
     * number of queries: one for the leave, none per user.
     *
     * @param  Collection<int, User>  $users
     * @return array{
     *     perUser: array<int, array{total: int, overCount: int, byType: array<string, array<string, int|null>>}>,
     *     teamByType: array<string, int>,
     *     teamTotal: int,
     *     staffOverLimit: int
     * }
     */
    public function summaryForYear(int $year, Collection $users): array
    {
        $userIds = $users->pluck('id')->map(fn ($id) => (int) $id)->all();

        [$yearStart, $nextYearStart] = $this->yearWindow($year);

        $leaves = $userIds === []
            ? collect()
            : StaffLeave::query()
                ->whereIn('user_id', $userIds)
                ->active()
                ->overlapping($yearStart, $nextYearStart)
                ->get();

        $byUser = $leaves->groupBy('user_id');

        $perUser = [];
        $teamByType = array_fill_keys(LeaveType::values(), 0);
        $teamTotal = 0;
        $staffOverLimit = 0;

        foreach ($userIds as $userId) {
            $byType = [];
            $total = 0;
            $overCount = 0;

            foreach (LeaveType::cases() as $type) {
                $taken = 0;
                $booked = 0;

                foreach ($byUser->get($userId, collect()) as $leave) {
                    if ($leave->type !== $type) {
                        continue;
                    }

                    $part = $this->daysInYear($leave, $year);
                    $taken += $part['taken'];
                    $booked += $part['booked'];
                }

                $used = $taken + $booked;
                $limit = $type->allowance();
                $over = $limit === null ? 0 : max(0, $used - $limit);

                if ($over > 0) {
                    $overCount++;
                }

                $byType[$type->value] = [
                    'used' => $used,
                    'taken' => $taken,
                    'booked' => $booked,
                    'limit' => $limit,
                    'remaining' => $limit === null ? null : $limit - $used,
                    'over' => $over,
                ];

                $total += $used;
                $teamByType[$type->value] += $used;
            }

            if ($overCount > 0) {
                $staffOverLimit++;
            }

            $teamTotal += $total;

            $perUser[$userId] = [
                'byType' => $byType,
                'total' => $total,
                'overCount' => $overCount,
            ];
        }

        return [
            'perUser' => $perUser,
            'teamByType' => $teamByType,
            'teamTotal' => $teamTotal,
            'staffOverLimit' => $staffOverLimit,
        ];
    }

    /**
     * What a prospective (or edited) leave would do to the allowance, per
     * calendar year it touches — a leave may span New Year.
     *
     * `$ignoreLeaveId` excludes the record being edited, so a leave is never
     * counted against itself.
     *
     * @return array<int, array{limit: int, existing: int, adding: int, total: int, over: int}>
     */
    public function projectionFor(
        int $userId,
        LeaveType $type,
        CarbonInterface|string $firstDay,
        CarbonInterface|string $returnDate,
        ?int $ignoreLeaveId = null,
    ): array {
        $limit = $type->allowance();

        if ($limit === null) {
            return [];
        }

        $first = $this->toDate($firstDay);
        $return = $this->toDate($returnDate);

        if ($return->lte($first)) {
            return [];
        }

        $projection = [];

        // The last day away is the day before the return date, so a leave
        // ending on 1 Jan does not reach into the new year.
        for ($year = $first->year; $year <= $return->copy()->subDay()->year; $year++) {
            [$yearStart, $nextYearStart] = $this->yearWindow($year);

            $from = $first->copy()->max($yearStart);
            $to = $return->copy()->min($nextYearStart);

            if ($to->lte($from)) {
                continue;
            }

            $adding = WorkingDays::between($from, $to);
            $existing = $this->usedDays($userId, $year, $type, $ignoreLeaveId);
            $total = $existing + $adding;

            $projection[$year] = [
                'limit' => $limit,
                'existing' => $existing,
                'adding' => $adding,
                'total' => $total,
                'over' => max(0, $total - $limit),
            ];
        }

        return $projection;
    }

    /**
     * Only the years this leave would push over the limit.
     *
     * @return array<int, array{limit: int, existing: int, adding: int, total: int, over: int}>
     */
    public function projectedOverage(
        int $userId,
        LeaveType $type,
        CarbonInterface|string $firstDay,
        CarbonInterface|string $returnDate,
        ?int $ignoreLeaveId = null,
    ): array {
        return array_filter(
            $this->projectionFor($userId, $type, $firstDay, $returnDate, $ignoreLeaveId),
            fn (array $year) => $year['over'] > 0,
        );
    }

    /**
     * The earliest calendar year that has any leave recorded, so the year
     * selector knows how far back to go.
     */
    public function earliestYearWithLeave(): int
    {
        $earliest = StaffLeave::query()->active()->min('first_day');

        return $earliest
            ? Carbon::parse($earliest)->year
            : now()->year;
    }

    // ================= internals =================

    /**
     * One leave's working days inside one calendar year, split at today.
     *
     * @return array{taken: int, booked: int, used: int}
     */
    protected function daysInYear(StaffLeave $leave, int $year): array
    {
        [$yearStart, $nextYearStart] = $this->yearWindow($year);

        $from = $leave->first_day->copy()->startOfDay()->max($yearStart);
        $to = $leave->return_date->copy()->startOfDay()->min($nextYearStart);

        if ($to->lte($from)) {
            return ['taken' => 0, 'booked' => 0, 'used' => 0];
        }

        $today = now()->startOfDay();

        // Split at today: everything before it has been served, the rest is
        // booked. Both halves use the same half-open rule, so they always sum
        // back to the whole.
        $takenEnd = $to->copy()->min($today);
        $bookedStart = $from->copy()->max($today);

        $taken = $takenEnd->gt($from) ? WorkingDays::between($from, $takenEnd) : 0;
        $booked = $to->gt($bookedStart) ? WorkingDays::between($bookedStart, $to) : 0;

        return ['taken' => $taken, 'booked' => $booked, 'used' => $taken + $booked];
    }

    /**
     * @return array{taken: int, booked: int, used: int}
     */
    protected function split(User|int $user, int $year, ?LeaveType $type, ?int $ignoreLeaveId): array
    {
        $userId = $user instanceof User ? $user->id : $user;

        [$yearStart, $nextYearStart] = $this->yearWindow($year);

        $leaves = StaffLeave::query()
            ->where('user_id', $userId)
            ->active()
            ->when($type, fn ($q) => $q->where('type', $type->value))
            ->when($ignoreLeaveId, fn ($q) => $q->whereKeyNot($ignoreLeaveId))
            ->overlapping($yearStart, $nextYearStart)
            ->get();

        $taken = 0;
        $booked = 0;

        foreach ($leaves as $leave) {
            $part = $this->daysInYear($leave, $year);
            $taken += $part['taken'];
            $booked += $part['booked'];
        }

        return ['taken' => $taken, 'booked' => $booked, 'used' => $taken + $booked];
    }

    /** @return array{0: Carbon, 1: Carbon} */
    protected function yearWindow(int $year): array
    {
        $start = Carbon::create($year, 1, 1)->startOfDay();

        return [$start, $start->copy()->addYear()];
    }

    protected function toDate(CarbonInterface|string $date): Carbon
    {
        return ($date instanceof CarbonInterface ? $date->copy() : Carbon::parse($date))->startOfDay();
    }
}
