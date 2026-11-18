<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Admin and super admin can manage content (events, schedules, announcements, documents).
        Gate::define('admin', fn (User $user) => $user->isAdmin());

        // Only the super admin can manage user accounts.
        Gate::define('manage-users', fn (User $user) => $user->isSuperAdmin());

        // Full SM Clinic module: schedule, bookings, and the client database.
        Gate::define('use-clinic', fn (User $user) => $user->canUseClinic());

        // Scoped "My Appointments" — clinicians, and anyone still holding
        // assignments after the flag was revoked.
        Gate::define('view-own-appointments', fn (User $user) => $user->hasClinicAssignments());

        // Staff Availability: recording leave and reading who is away. Admins
        // and the super admin only — can_book does not grant it.
        Gate::define('manage-availability', fn (User $user) => $user->isAdmin());

        // Adding and maintaining roster-only staff. Same holders as
        // manage-availability, kept separate so the two can diverge later
        // without hunting down call sites.
        Gate::define('manage-roster', fn (User $user) => $user->isAdmin());
    }
}
