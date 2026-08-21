<?php

namespace App\Policies;

use App\Models\ClinicClient;
use App\Models\User;

/**
 * The client database is full-module only. A clinician can see the client name
 * on their own appointments (they need it to treat), but never browses this.
 */
class ClinicClientPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canUseClinic();
    }

    public function view(User $user, ClinicClient $client): bool
    {
        return $user->canUseClinic();
    }

    public function create(User $user): bool
    {
        return $user->canUseClinic();
    }

    public function update(User $user, ClinicClient $client): bool
    {
        return $user->canUseClinic();
    }

    public function delete(User $user, ClinicClient $client): bool
    {
        return $user->isAdmin();
    }
}
