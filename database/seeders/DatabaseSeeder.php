<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * ⚠️ NEVER run a plain `db:seed` against production. SuperAdminSeeder uses
     * updateOrCreate, so it would reset the super admin's password to whatever
     * SUPER_ADMIN_PASSWORD currently holds — silently locking Jason out of his
     * own account if that value has drifted.
     *
     * Nothing in the deploy pipeline runs seeders, and nothing should be added
     * that does: the clinic services that used to depend on a seeder are now
     * guaranteed by the 2026_11_18_200001_ensure_clinic_services_exist data
     * migration instead.
     */
    public function run(): void
    {
        $this->call([
            SuperAdminSeeder::class,
            ClinicServiceSeeder::class,
        ]);
    }
}
