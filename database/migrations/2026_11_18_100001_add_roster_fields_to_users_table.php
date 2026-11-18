<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 6 — roster-only staff.
 *
 * Staff who don't use the app yet still need to be assignable to events, gym
 * sessions and leave, so they live in `users` like everyone else. They differ
 * only in having no way in: `login_enabled = false`, and often no email.
 *
 * `email` therefore becomes nullable while keeping its unique index — both
 * MySQL and SQLite allow any number of NULLs in a unique column. `password`
 * stays NOT NULL; roster entries get a random hash they can never use.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->string('position', 100)->nullable()->after('name');
            $table->boolean('login_enabled')->default(true)->after('is_active');
        });
    }

    /**
     * Only reversible while no roster-only staff exist.
     *
     * They are the whole point of the nullable email, so once any have been
     * added, `email` cannot go back to NOT NULL and MySQL rejects the ALTER
     * with "Invalid use of NULL value". Verified against MySQL 8.0.39.
     *
     * Checking first turns that into an explicit message, and stops before
     * anything is altered. Note that a rollback of the whole Phase 6 batch
     * drops `staff_leaves` first, so leave records are lost before this runs
     * either way — restore from a database snapshot instead of rolling back a
     * live deployment.
     */
    public function down(): void
    {
        $withoutEmail = DB::table('users')->whereNull('email')->count();

        if ($withoutEmail > 0) {
            throw new RuntimeException(
                "Cannot reverse this migration: {$withoutEmail} user(s) have no email address "
                .'(roster-only staff). Give them an email address or remove them first, or '
                .'restore the database from a snapshot.'
            );
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['position', 'login_enabled']);
            $table->string('email')->nullable(false)->change();
        });
    }
};
