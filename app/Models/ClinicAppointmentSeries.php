<?php

namespace App\Models;

use App\Enums\Recurrence;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClinicAppointmentSeries extends Model
{
    use HasFactory;

    protected $table = 'clinic_appointment_series';

    protected $fillable = [
        'clinic_client_id',
        'clinic_service_id',
        'assigned_staff_id',
        'booked_by',
        'start_time',
        'duration_minutes',
        'recurrence',
        'days_of_week',
        'start_date',
        'end_date',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'recurrence' => Recurrence::class,
            'days_of_week' => 'array',
            'start_date' => 'date',
            'end_date' => 'date',
            'duration_minutes' => 'integer',
        ];
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(ClinicAppointment::class, 'series_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(ClinicClient::class, 'clinic_client_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(ClinicService::class, 'clinic_service_id');
    }

    public function clinician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_staff_id');
    }

    /**
     * Every date this series falls on, between start_date and end_date.
     * Occurrences are materialised into clinic_appointments from this list.
     *
     * @return array<int, Carbon>
     */
    public function occurrenceDates(): array
    {
        $dates = [];

        for ($day = $this->start_date->copy(); $day->lte($this->end_date); $day->addDay()) {
            if ($this->occursOn($day)) {
                $dates[] = $day->copy();
            }
        }

        return $dates;
    }

    public function occursOn(CarbonInterface $date): bool
    {
        if ($date->lt($this->start_date) || $date->gt($this->end_date)) {
            return false;
        }

        return match ($this->recurrence) {
            Recurrence::Daily => true,
            Recurrence::Weekly => in_array($date->dayOfWeek, $this->days_of_week ?? [], true),
            default => $date->isSameDay($this->start_date),
        };
    }
}
