<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Account-setup links live in their own table, separate from
 * `password_reset_tokens`.
 *
 * Two reasons. A setup link is good for 24 hours and a reset link for one, and
 * the expiry is a property of the broker, not the token — sharing one table
 * would let a stale reset token be redeemed through the setup page for a day.
 * Keeping them apart also means an invite survives the staff member trying
 * "forgot password" first.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('password_invite_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_invite_tokens');
    }
};
