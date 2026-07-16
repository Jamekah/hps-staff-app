<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gym_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('client_type', ['national_federation', 'external_client']);
            $table->string('client_name');
            $table->enum('studio', ['1', '2']);
            $table->date('start_date');
            $table->date('end_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->enum('recurrence', ['none', 'daily', 'weekly'])->default('none');
            $table->json('days_of_week')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['start_date', 'end_date']);
        });

        Schema::create('gym_schedule_staff', function (Blueprint $table) {
            $table->foreignId('gym_schedule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->primary(['gym_schedule_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gym_schedule_staff');
        Schema::dropIfExists('gym_schedules');
    }
};
