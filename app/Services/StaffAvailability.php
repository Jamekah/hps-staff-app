<?php

namespace App\Services;

use App\Models\ClinicAppointment;
use App\Models\Event;
use App\Models\GymSchedule;
use App\Models\StaffLeave;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * The application's general conflict engine.
 *
 * Answers "is this person free between these two times?" across every module
 * that can occupy someone's day: events they're assigned to, gym sessions
 * they're allocated to, clinic appointments assigned to them, and leave
 * recorded against them.
 *
 * Deliberately module-agnostic — it takes a user id and a window, so future
 * features can reuse it without modification.
 *
 * All comparisons use Pacific/Port_Moresby wall-clock time, matching how the
 * app stores datetimes throughout.
 */
class StaffAvailability
{
    /**
     * True when nothing in the user's schedule overlaps [$start, $end).
     *
     * @param  int|null  $ignoreAppointmentId  Clinic appointment to disregard,
     *                                         so editing one doesn't clash with itself.
     */
    public function isFree(int $userId, CarbonInterface $start, CarbonInterface $end, ?int $ignoreAppointmentId = null): bool
    {
        return $this->conflicts($userId, $start, $end, $ignoreAppointmentId)->isEmpty();
    }

    /**
     * Everything in the user's schedule that overlaps the window. Each entry is
     * ['type' => 'event'|'gym'|'clinic'|'leave', 'label' => string, 'starts_at' => Carbon, 'ends_at' => Carbon].
     *
     * Leave labels never name the leave type: clinic bookers and other admins
     * see these strings, and why someone is away is theirs alone.
     */
    public function conflicts(int $userId, CarbonInterface $start, CarbonInterface $end, ?int $ignoreAppointmentId = null): Collection
    {
        return collect()
            ->concat($this->eventConflicts($userId, $start, $end))
            ->concat($this->gymConflicts($userId, $start, $end))
            ->concat($this->clinicConflicts($userId, $start, $end, $ignoreAppointmentId))
            ->concat($this->leaveConflicts($userId, $start, $end))
            ->values();
    }

    /**
     * Everything the person is already committed to in the window, ignoring
     * leave. Drives the conflict report shown while recording leave, which is
     * about existing assignments rather than the leave being recorded.
     */
    public function assignmentConflicts(int $userId, CarbonInterface $start, CarbonInterface $end): Collection
    {
        return $this->conflicts($userId, $start, $end)
            ->reject(fn (array $conflict) => $conflict['type'] === 'leave')
            ->values();
    }

    /**
     * The non-cancelled leave overlapping the window, if any.
     *
     * Leave-only, ignoring every other kind of clash: events and one-off gym
     * sessions block on leave alone, since overlapping commitments elsewhere
     * are legitimate there.
     */
    public function leaveFor(int $userId, CarbonInterface $start, CarbonInterface $end): ?StaffLeave
    {
        return StaffLeave::query()
            ->where('user_id', $userId)
            ->active()
            ->overlapping($start, $end)
            ->orderBy('first_day')
            ->first();
    }

    /**
     * Which of the given users are on leave for any part of the window.
     *
     * @param  array<int>  $userIds
     * @return array<int, bool>  Keyed by user id; every id asked for is present.
     */
    public function onLeaveMap(array $userIds, CarbonInterface $start, CarbonInterface $end): array
    {
        if ($userIds === []) {
            return [];
        }

        $onLeave = StaffLeave::query()
            ->whereIn('user_id', $userIds)
            ->active()
            ->overlapping($start, $end)
            ->pluck('user_id')
            ->unique()
            ->all();

        $map = [];

        foreach ($userIds as $userId) {
            $map[(int) $userId] = in_array((int) $userId, array_map('intval', $onLeave), true);
        }

        return $map;
    }

    /**
     * The overlapping leave for each of the given users, keyed by user id.
     * Only users actually on leave appear, so the caller can both test
     * membership and show the back date.
     *
     * @param  array<int>  $userIds
     * @return array<int, StaffLeave>
     */
    public function leaveMap(array $userIds, CarbonInterface $start, CarbonInterface $end): array
    {
        if ($userIds === []) {
            return [];
        }

        return StaffLeave::query()
            ->whereIn('user_id', $userIds)
            ->active()
            ->overlapping($start, $end)
            ->orderBy('first_day')
            ->get()
            ->keyBy('user_id')
            ->all();
    }

    /**
     * Which of the given users are free for the window.
     *
     * @param  array<int>  $userIds
     * @return array<int, bool>  Keyed by user id.
     */
    public function freeMap(array $userIds, CarbonInterface $start, CarbonInterface $end, ?int $ignoreAppointmentId = null): array
    {
        $map = [];

        foreach ($userIds as $userId) {
            $map[$userId] = $this->isFree($userId, $start, $end, $ignoreAppointmentId);
        }

        return $map;
    }

    /**
     * Half-open overlap: touching intervals do not clash, so a 09:00–10:00 and
     * a 10:00–11:00 booking sit back to back happily.
     */
    protected function overlaps(CarbonInterface $aStart, CarbonInterface $aEnd, CarbonInterface $bStart, CarbonInterface $bEnd): bool
    {
        return $aStart->lt($bEnd) && $bStart->lt($aEnd);
    }

    protected function eventConflicts(int $userId, CarbonInterface $start, CarbonInterface $end): Collection
    {
        return Event::query()
            ->whereHas('staff', fn ($q) => $q->where('users.id', $userId))
            ->where('starts_at', '<', $end)
            ->where('ends_at', '>', $start)
            ->get()
            ->map(fn (Event $event) => [
                'type' => 'event',
                'label' => $event->name,
                'starts_at' => $event->starts_at,
                'ends_at' => $event->ends_at,
            ]);
    }

    /**
     * Gym sessions are stored one row per series, so occurrences are expanded
     * per day across the window using the existing query the gym page uses.
     */
    protected function gymConflicts(int $userId, CarbonInterface $start, CarbonInterface $end): Collection
    {
        $conflicts = collect();

        $day = $start->copy()->startOfDay();
        $lastDay = $end->copy()->startOfDay();

        while ($day->lte($lastDay)) {
            foreach (GymSchedule::occurrencesOn($day) as $session) {
                if (! $session->staff->contains('id', $userId)) {
                    continue;
                }

                $sessionStart = $day->copy()->setTimeFromTimeString($session->start_time);
                $sessionEnd = $day->copy()->setTimeFromTimeString($session->end_time);

                if ($this->overlaps($sessionStart, $sessionEnd, $start, $end)) {
                    $conflicts->push([
                        'type' => 'gym',
                        'label' => $session->name.' (Studio '.$session->studio.')',
                        'starts_at' => $sessionStart,
                        'ends_at' => $sessionEnd,
                    ]);
                }
            }

            $day->addDay();
        }

        return $conflicts;
    }

    protected function clinicConflicts(int $userId, CarbonInterface $start, CarbonInterface $end, ?int $ignoreAppointmentId): Collection
    {
        return ClinicAppointment::query()
            ->where('assigned_staff_id', $userId)
            ->blocking()
            ->when($ignoreAppointmentId, fn ($q) => $q->whereKeyNot($ignoreAppointmentId))
            ->where('starts_at', '<', $end)
            ->where('ends_at', '>', $start)
            ->with(['client', 'service'])
            ->get()
            ->map(fn (ClinicAppointment $appointment) => [
                'type' => 'clinic',
                'label' => $appointment->client?->name.' — '.($appointment->service?->name ?? 'appointment'),
                'starts_at' => $appointment->starts_at,
                'ends_at' => $appointment->ends_at,
            ]);
    }

    /**
     * Leave occupies whole days: [first_day 00:00, return_date 00:00). The
     * same half-open rule as everything else, so "back Wednesday" leaves
     * Wednesday 00:00 onwards free.
     */
    protected function leaveConflicts(int $userId, CarbonInterface $start, CarbonInterface $end): Collection
    {
        return StaffLeave::query()
            ->where('user_id', $userId)
            ->active()
            ->overlapping($start, $end)
            ->orderBy('first_day')
            ->get()
            ->map(fn (StaffLeave $leave) => [
                'type' => 'leave',
                'label' => $leave->publicLabel(),
                'starts_at' => $leave->startsAt(),
                'ends_at' => $leave->endsAt(),
            ]);
    }

    /**
     * Convenience for building a window from a date and a start time plus a
     * duration in minutes.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function window(CarbonInterface $date, string $startTime, int $durationMinutes): array
    {
        $start = $date->copy()->setTimeFromTimeString($startTime);

        return [$start, $start->copy()->addMinutes($durationMinutes)];
    }
}
