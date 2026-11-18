<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['position', 'login_enabled']);
            $table->string('email')->nullable(false)->change();
        });
    }
};
