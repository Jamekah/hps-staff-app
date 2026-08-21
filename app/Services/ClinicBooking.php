<?php

namespace App\Services;

use App\Models\ClinicAppointment;
use App\Models\ClinicAppointmentSeries;
use App\Models\ClinicService;
use App\Notifications\ClinicAppointmentAssigned;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Creates clinic bookings and applies the module's clash rules.
 *
 * The two rules differ deliberately:
 *  - single bookings are hard-blocked on a clash;
 *  - recurring rehab series are allowed but each clashing occurrence is
 *    flagged, so the booker can see the damage and decide.
 */
class ClinicBooking
{
    /** Clinic operating hours — bookings outside these are rejected. */
    public const OPENS_AT = '08:00';

    public const CLOSES_AT = '16:00';

    public function __construct(protected StaffAvailability $availability)
    {
    }

    /**
     * Book a one-off appointment. Throws when the clinician is not free.
     *
     * @throws ClinicClashException
     */
    public function bookSingle(array $attributes, bool $notify = true): ClinicAppointment
    {
        $start = Carbon::parse($attributes['starts_at']);
        $end = Carbon::parse($attributes['ends_at']);

        if (! $this->availability->isFree($attributes['assigned_staff_id'], $start, $end)) {
            throw new ClinicClashException(
                'That clinician is not free at the selected time.'
            );
        }

        $appointment = ClinicAppointment::create([
            ...$attributes,
            'has_clash' => false,
        ]);

        if ($notify) {
            $this->notifyAssignment($appointment);
        }

        return $appointment;
    }

    /**
     * Create a recurring series and materialise one appointment per occurrence.
     * Clashing occurrences are flagged rather than rejected.
     *
     * @return array{series: ClinicAppointmentSeries, clashes: array<int, Carbon>}
     */
    public function bookSeries(array $seriesAttributes, bool $notify = true): array
    {
        return DB::transaction(function () use ($seriesAttributes, $notify) {
            $series = ClinicAppointmentSeries::create($seriesAttributes);

            $clashes = [];

            foreach ($series->occurrenceDates() as $date) {
                [$start, $end] = StaffAvailability::window(
                    $date,
                    $series->start_time,
                    $series->duration_minutes
                );

                $hasClash = ! $this->availability->isFree($series->assigned_staff_id, $start, $end);

                if ($hasClash) {
                    $clashes[] = $start->copy();
                }

                ClinicAppointment::create([
                    'clinic_client_id' => $series->clinic_client_id,
                    'clinic_service_id' => $series->clinic_service_id,
                    'assigned_staff_id' => $series->assigned_staff_id,
                    'booked_by' => $series->booked_by,
                    'series_id' => $series->id,
                    'starts_at' => $start,
                    'ends_at' => $end,
                    'note' => $series->note,
                    'has_clash' => $hasClash,
                ]);
            }

            if ($notify) {
                // One summary notice for the whole series, not one per occurrence.
                $this->notifySeriesAssignment($series);
            }

            return ['series' => $series, 'clashes' => $clashes];
        });
    }

    /**
     * Preview which occurrences of a prospective series would clash, without
     * writing anything — drives the confirmation summary in the booking form.
     *
     * @return array<int, Carbon>
     */
    public function previewSeriesClashes(ClinicAppointmentSeries $draft): array
    {
        $clashes = [];

        foreach ($draft->occurrenceDates() as $date) {
            [$start, $end] = StaffAvailability::window(
                $date,
                $draft->start_time,
                $draft->duration_minutes
            );

            if (! $this->availability->isFree($draft->assigned_staff_id, $start, $end)) {
                $clashes[] = $start->copy();
            }
        }

        return $clashes;
    }

    /**
     * Clinicians selectable for a window: those with is_clinician who are both
     * active and free. Returns [userId => ['user' => User, 'free' => bool]] so
     * the UI can grey out the clashing ones rather than hide them.
     */
    public function clinicianAvailability(CarbonInterface $start, CarbonInterface $end, ?int $ignoreAppointmentId = null): array
    {
        $clinicians = User::query()
            ->where('is_clinician', true)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $result = [];

        foreach ($clinicians as $clinician) {
            $result[$clinician->id] = [
                'user' => $clinician,
                'free' => $this->availability->isFree($clinician->id, $start, $end, $ignoreAppointmentId),
            ];
        }

        return $result;
    }

    /**
     * End time implied by a service's default duration — the form pre-fills
     * with this, but the booker may override it.
     */
    public function defaultEnd(ClinicService $service, CarbonInterface $start): Carbon
    {
        return $start->copy()->addMinutes($service->default_duration_minutes);
    }

    protected function notifyAssignment(ClinicAppointment $appointment): void
    {
        $clinician = $appointment->clinician;

        if ($clinician && $clinician->is_active) {
            $clinician->notify(new ClinicAppointmentAssigned($appointment));
        }
    }

    protected function notifySeriesAssignment(ClinicAppointmentSeries $series): void
    {
        $clinician = $series->clinician;

        if ($clinician && $clinician->is_active) {
            $clinician->notify(new ClinicAppointmentAssigned(
                $series->appointments()->orderBy('starts_at')->first(),
                $series->appointments()->count()
            ));
        }
    }
}
