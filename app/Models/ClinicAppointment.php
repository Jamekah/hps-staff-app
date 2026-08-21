<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClinicAppointment extends Model
{
    use HasFactory;

    protected $fillable = [
        'clinic_client_id',
        'clinic_service_id',
        'assigned_staff_id',
        'booked_by',
        'series_id',
        'starts_at',
        'ends_at',
        'note',
        'status',
        'has_clash',
        'status_prompt_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'status' => AppointmentStatus::class,
            'has_clash' => 'boolean',
            'status_prompt_sent_at' => 'datetime',
        ];
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

    public function booker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'booked_by');
    }

    public function series(): BelongsTo
    {
        return $this->belongsTo(ClinicAppointmentSeries::class, 'series_id');
    }

    public function scopeOnDay(Builder $query, CarbonInterface $date): Builder
    {
        return $query->whereBetween('starts_at', [
            $date->copy()->startOfDay(),
            $date->copy()->endOfDay(),
        ]);
    }

    public function scopeBetween(Builder $query, CarbonInterface $from, CarbonInterface $to): Builder
    {
        return $query->whereBetween('starts_at', [$from, $to]);
    }

    /** Appointments that occupy a clinician's time. */
    public function scopeBlocking(Builder $query): Builder
    {
        return $query->where('status', AppointmentStatus::Scheduled);
    }

    public function isCancelled(): bool
    {
        return $this->status === AppointmentStatus::Cancelled;
    }

    public function isRecurring(): bool
    {
        return $this->series_id !== null;
    }
}
