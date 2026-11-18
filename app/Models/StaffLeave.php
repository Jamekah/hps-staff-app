<?php

namespace App\Models;

use App\Enums\LeaveType;
use App\Observers\StaffLeaveObserver;
use App\Services\WorkingDays;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A whole-day absence: [first_day 00:00, return_date 00:00).
 *
 * `return_date` is "back at work on" and is exclusive, so a one-day leave has
 * return_date = first_day + 1. Keeping the window half-open means it drops
 * straight into StaffAvailability's existing overlap rule.
 */
#[ObservedBy(StaffLeaveObserver::class)]
class StaffLeave extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'first_day',
        'return_date',
        'note',
        'recorded_by',
        'cancelled_at',
        'cancelled_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => LeaveType::class,
            'first_day' => 'date',
            'return_date' => 'date',
            'cancelled_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /** Cancelled leave is kept as history but never blocks or flags anything. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('cancelled_at');
    }

    public function scopeCancelled(Builder $query): Builder
    {
        return $query->whereNotNull('cancelled_at');
    }

    /**
     * Leave whose half-open window overlaps [$start, $end). Dates are compared
     * as whole days: the leave occupies first_day 00:00 up to (not including)
     * return_date 00:00.
     */
    public function scopeOverlapping(Builder $query, CarbonInterface $start, CarbonInterface $end): Builder
    {
        // Against a datetime window this reduces to two whole-day comparisons:
        // the latest day the leave may start on, and the earliest day it may
        // return on. When $end lands exactly on midnight, a leave starting that
        // day does not overlap — the window has already closed.
        $lastStartDay = $end->copy()->startOfDay()->lt($end)
            ? $end->copy()->startOfDay()
            : $end->copy()->subDay()->startOfDay();

        return $query
            ->whereDate('first_day', '<=', $lastStartDay->toDateString())
            ->whereDate('return_date', '>', $start->copy()->startOfDay()->toDateString());
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    /** Inclusive last day away — what people actually mean by "until". */
    public function lastDay(): CarbonInterface
    {
        return $this->return_date->copy()->subDay();
    }

    public function calendarDays(): int
    {
        return (int) $this->first_day->diffInDays($this->return_date);
    }

    public function workingDays(): int
    {
        return WorkingDays::between($this->first_day, $this->return_date);
    }

    public function coversDate(CarbonInterface $date): bool
    {
        $day = $date->copy()->startOfDay();

        return $day->gte($this->first_day->copy()->startOfDay())
            && $day->lt($this->return_date->copy()->startOfDay());
    }

    /** Window as datetimes, for the conflict engine. */
    public function startsAt(): CarbonInterface
    {
        return $this->first_day->copy()->startOfDay();
    }

    public function endsAt(): CarbonInterface
    {
        return $this->return_date->copy()->startOfDay();
    }

    /**
     * The label shown outside the Availability page. Never names the type.
     */
    public function publicLabel(): string
    {
        return 'On leave (back '.$this->return_date->format('j M').')';
    }
}
