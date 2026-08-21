<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Two clinic privileges, orthogonal to the `role` enum: a user may hold
     * either, both, or neither.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('can_book')->default(false)->after('is_active');
            $table->boolean('is_clinician')->default(false)->after('can_book');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['can_book', 'is_clinician']);
        });
    }
};
