<?php

namespace App\Policies;

use App\Models\StaffLeave;
use App\Models\User;

/**
 * Leave is an admin record. Staff — including staff with clinic booking
 * rights — never see the Availability page, and never see why anyone is away.
 */
class StaffLeavePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, StaffLeave $leave): bool
    {
        return $user->isAdmin();
    }

    public function cancel(User $user, StaffLeave $leave): bool
    {
        return $user->isAdmin() && ! $leave->isCancelled();
    }
}
