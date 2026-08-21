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
    }
}
