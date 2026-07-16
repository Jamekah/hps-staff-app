<?php

namespace App\Models;

use App\Enums\ClientType;
use App\Enums\Recurrence;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

class GymSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'client_type',
        'client_name',
        'studio',
        'start_date',
        'end_date',
        'start_time',
        'end_time',
        'recurrence',
        'days_of_week',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'client_type' => ClientType::class,
            'recurrence' => Recurrence::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'days_of_week' => 'array',
        ];
    }

    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'gym_schedule_staff');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Series whose date window includes the given date.
     */
    public function scopeActiveOn(Builder $query, CarbonInterface $date): Builder
    {
        return $query
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date);
    }

    /**
     * Whether this series has an occurrence on the given date.
     *
     * Recurrence rules: `none` occurs on start_date only; `daily` occurs every
     * day in the window; `weekly` occurs on the selected weekdays (0 = Sunday,
     * matching Carbon's dayOfWeek).
     */
    public function occursOn(CarbonInterface $date): bool
    {
        if ($date->lt($this->start_date->startOfDay()) || $date->gt($this->end_date->endOfDay())) {
            return false;
        }

        return match ($this->recurrence) {
            Recurrence::None => $date->isSameDay($this->start_date),
            Recurrence::Daily => true,
            Recurrence::Weekly => in_array($date->dayOfWeek, $this->days_of_week ?? [], true),
        };
    }

    /**
     * All sessions occurring on the given date, with staff eager-loaded.
     * Phase 3 reuses this to find sessions (and allocated staff) to notify.
     */
    public static function occurrencesOn(CarbonInterface $date): Collection
    {
        return static::query()
            ->activeOn($date)
            ->with('staff')
            ->orderBy('start_time')
            ->get()
            ->filter(fn (self $schedule) => $schedule->occursOn($date))
            ->values();
    }
}
