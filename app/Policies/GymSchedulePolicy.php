<?php

namespace App\Policies;

use App\Models\GymSchedule;
use App\Models\User;

class GymSchedulePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, GymSchedule $gymSchedule): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, GymSchedule $gymSchedule): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, GymSchedule $gymSchedule): bool
    {
        return $user->isAdmin();
    }
}
