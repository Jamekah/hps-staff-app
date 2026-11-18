<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Leave is recorded in whole days only, as the half-open window
 * [first_day 00:00, return_date 00:00) in app time — matching the overlap
 * rule StaffAvailability already uses, so "back Wednesday" means free from
 * Wednesday 00:00 with no special casing anywhere.
 *
 * Cancelling keeps the row as history rather than deleting it; cancelled
 * leave never blocks or flags anything.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_leaves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['annual', 'sick', 'training', 'duty_travel', 'other']);
            $table->date('first_day');
            $table->date('return_date');
            $table->text('note')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'first_day', 'return_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_leaves');
    }
};
