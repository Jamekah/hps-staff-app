<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinic_services', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedSmallInteger('default_duration_minutes')->default(30);
            $table->boolean('allows_recurrence')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('clinic_clients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('organization')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('name');
            $table->index('organization');
        });

        // Parent row for recurring series (Rehabilitation only). Child
        // occurrences are materialised into clinic_appointments.
        Schema::create('clinic_appointment_series', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('clinic_service_id')->constrained();
            $table->foreignId('assigned_staff_id')->constrained('users');
            $table->foreignId('booked_by')->constrained('users');
            $table->time('start_time');
            $table->unsignedSmallInteger('duration_minutes');
            $table->enum('recurrence', ['daily', 'weekly']);
            $table->json('days_of_week')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('clinic_appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('clinic_service_id')->constrained();
            $table->foreignId('assigned_staff_id')->constrained('users');
            $table->foreignId('booked_by')->constrained('users');
            $table->foreignId('series_id')->nullable()
                ->constrained('clinic_appointment_series')->cascadeOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->text('note')->nullable();
            $table->enum('status', ['scheduled', 'cancelled', 'completed', 'no_show'])
                ->default('scheduled');
            // Snapshot of the clash check at booking time — recurring series are
            // flagged rather than blocked.
            $table->boolean('has_clash')->default(false);
            $table->timestamp('status_prompt_sent_at')->nullable();
            $table->timestamps();

            $table->index(['assigned_staff_id', 'starts_at']);
            $table->index('starts_at');
            $table->index('clinic_client_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_appointments');
        Schema::dropIfExists('clinic_appointment_series');
        Schema::dropIfExists('clinic_clients');
        Schema::dropIfExists('clinic_services');
    }
};
