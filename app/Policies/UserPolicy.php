<?php

namespace App\Policies;

use App\Models\User;

/**
 * Phase 6 splits user management in two.
 *
 * The super admin keeps everything: roles, clinic flags, invites, deletion,
 * and editing people who can sign in. Admins get the roster only — adding,
 * editing and (de)activating staff who have no login — because they are the
 * ones who know who is actually on the team.
 *
 * The rule throughout: an admin may never touch a user who holds a login.
 */
class UserPolicy
{
    /** The Users page itself stays super-admin only. */
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    /** Admins may add roster staff; the role and flags are forced, not chosen. */
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, User $target): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isAdmin() && $target->isRosterOnly();
    }

    /** Activate / deactivate. Nobody may do it to themselves. */
    public function toggleActive(User $user, User $target): bool
    {
        if ($user->id === $target->id) {
            return false;
        }

        return $this->update($user, $target);
    }

    /** Roles and clinic flags are the super admin's alone. */
    public function managePrivileges(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    /** Turning a roster entry into a login account. */
    public function sendInvite(User $user, User $target): bool
    {
        return $user->isSuperAdmin() && $target->isRosterOnly();
    }

    public function delete(User $user, User $target): bool
    {
        return $user->isSuperAdmin() && $user->id !== $target->id;
    }
}
