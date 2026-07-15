<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    /**
     * Seed the super admin account from SUPER_ADMIN_* environment variables.
     */
    public function run(): void
    {
        $email = config('superadmin.email');
        $password = config('superadmin.password');

        if (! $email || ! $password) {
            $this->command?->warn('SUPER_ADMIN_EMAIL / SUPER_ADMIN_PASSWORD not set — skipping super admin seeding.');

            return;
        }

        User::updateOrCreate(
            ['email' => $email],
            [
                'name' => config('superadmin.name'),
                'password' => Hash::make($password),
                'role' => Role::SuperAdmin,
                'is_active' => true,
            ]
        );

        $this->command?->info("Super admin seeded: {$email}");
    }
}
