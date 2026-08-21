<?php

namespace App\Policies;

use App\Models\ClinicAppointment;
use App\Models\User;

/**
 * Clinic data is confidential: only full-module users (admins, or staff granted
 * can_book) may browse it. A clinician sees strictly their own appointments.
 */
class ClinicAppointmentPolicy
{
    /** Browse the full clinic schedule. */
    public function viewAny(User $user): bool
    {
        return $user->canUseClinic();
    }

    public function view(User $user, ClinicAppointment $appointment): bool
    {
        return $user->canUseClinic() || $this->isAssignedTo($user, $appointment);
    }

    public function create(User $user): bool
    {
        return $user->canUseClinic();
    }

    public function update(User $user, ClinicAppointment $appointment): bool
    {
        return $user->canUseClinic();
    }

    public function delete(User $user, ClinicAppointment $appointment): bool
    {
        return $user->canUseClinic();
    }

    /**
     * Cancelling is open to bookers/admins and to the assigned clinician for
     * their own appointment.
     */
    public function cancel(User $user, ClinicAppointment $appointment): bool
    {
        return $user->canUseClinic() || $this->isAssignedTo($user, $appointment);
    }

    /**
     * Setting completed / no-show is the assigned clinician's job, but bookers
     * and admins may also record an outcome.
     */
    public function setOutcome(User $user, ClinicAppointment $appointment): bool
    {
        return $user->canUseClinic() || $this->isAssignedTo($user, $appointment);
    }

    protected function isAssignedTo(User $user, ClinicAppointment $appointment): bool
    {
        return $appointment->assigned_staff_id === $user->id;
    }
}
