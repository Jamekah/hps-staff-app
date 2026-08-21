<?php

namespace Tests\Feature\Clinic;

use App\Enums\AppointmentStatus;
use App\Models\ClinicAppointment;
use App\Models\User;
use App\Notifications\ClinicAppointmentReminder;
use App\Notifications\ClinicStatusPrompt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ClinicNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        Cache::flush();
    }

    // ---------- 60-minute reminder ----------

    public function test_the_reminder_targets_appointments_starting_in_about_an_hour(): void
    {
        $clinician = User::factory()->clinician()->create();

        $appointment = ClinicAppointment::factory()
            ->at(now()->addMinutes(62))
            ->create(['assigned_staff_id' => $clinician->id]);

        $this->artisan('clinic:notify-upcoming')->assertSuccessful();

        Notification::assertSentTo($clinician, ClinicAppointmentReminder::class);
    }

    public function test_appointments_outside_the_window_are_not_reminded(): void
    {
        $tooSoon = User::factory()->clinician()->create();
        $tooFar = User::factory()->clinician()->create();

        ClinicAppointment::factory()->at(now()->addMinutes(30))
            ->create(['assigned_staff_id' => $tooSoon->id]);
        ClinicAppointment::factory()->at(now()->addMinutes(70))
            ->create(['assigned_staff_id' => $tooFar->id]);

        $this->artisan('clinic:notify-upcoming')->assertSuccessful();

        Notification::assertNotSentTo($tooSoon, ClinicAppointmentReminder::class);
        Notification::assertNotSentTo($tooFar, ClinicAppointmentReminder::class);
    }

    public function test_cancelled_appointments_are_not_reminded(): void
    {
        $clinician = User::factory()->clinician()->create();

        ClinicAppointment::factory()
            ->at(now()->addMinutes(62))
            ->cancelled()
            ->create(['assigned_staff_id' => $clinician->id]);

        $this->artisan('clinic:notify-upcoming')->assertSuccessful();

        Notification::assertNotSentTo($clinician, ClinicAppointmentReminder::class);
    }

    public function test_the_reminder_is_not_sent_twice_within_the_dedupe_window(): void
    {
        $clinician = User::factory()->clinician()->create();

        ClinicAppointment::factory()
            ->at(now()->addMinutes(62))
            ->create(['assigned_staff_id' => $clinician->id]);

        $this->artisan('clinic:notify-upcoming')->assertSuccessful();
        $this->artisan('clinic:notify-upcoming')->assertSuccessful();

        Notification::assertSentToTimes($clinician, ClinicAppointmentReminder::class, 1);
    }

    public function test_only_the_assigned_clinician_is_reminded(): void
    {
        $assigned = User::factory()->clinician()->create();
        $other = User::factory()->clinician()->create();

        ClinicAppointment::factory()
            ->at(now()->addMinutes(62))
            ->create(['assigned_staff_id' => $assigned->id]);

        $this->artisan('clinic:notify-upcoming')->assertSuccessful();

        Notification::assertSentTo($assigned, ClinicAppointmentReminder::class);
        Notification::assertNotSentTo($other, ClinicAppointmentReminder::class);
    }

    // ---------- Post-appointment status prompt ----------

    public function test_an_elapsed_scheduled_appointment_prompts_the_clinician(): void
    {
        $clinician = User::factory()->clinician()->create();

        $appointment = ClinicAppointment::factory()
            ->at(now()->subHours(2))
            ->create(['assigned_staff_id' => $clinician->id]);

        $this->artisan('clinic:prompt-status')->assertSuccessful();

        Notification::assertSentTo($clinician, ClinicStatusPrompt::class);

        $this->assertNotNull($appointment->fresh()->status_prompt_sent_at);
    }

    public function test_the_status_prompt_is_never_sent_twice(): void
    {
        $clinician = User::factory()->clinician()->create();

        ClinicAppointment::factory()
            ->at(now()->subHours(2))
            ->create(['assigned_staff_id' => $clinician->id]);

        $this->artisan('clinic:prompt-status')->assertSuccessful();
        $this->artisan('clinic:prompt-status')->assertSuccessful();

        Notification::assertSentToTimes($clinician, ClinicStatusPrompt::class, 1);
    }

    public function test_appointments_that_have_not_finished_are_not_prompted(): void
    {
        $clinician = User::factory()->clinician()->create();

        ClinicAppointment::factory()
            ->at(now()->addHour())
            ->create(['assigned_staff_id' => $clinician->id]);

        $this->artisan('clinic:prompt-status')->assertSuccessful();

        Notification::assertNotSentTo($clinician, ClinicStatusPrompt::class);
    }

    public function test_appointments_already_given_an_outcome_are_not_prompted(): void
    {
        $completed = User::factory()->clinician()->create();
        $cancelled = User::factory()->clinician()->create();

        ClinicAppointment::factory()->at(now()->subHours(2))->completed()
            ->create(['assigned_staff_id' => $completed->id]);
        ClinicAppointment::factory()->at(now()->subHours(2))->cancelled()
            ->create(['assigned_staff_id' => $cancelled->id]);

        $this->artisan('clinic:prompt-status')->assertSuccessful();

        Notification::assertNotSentTo($completed, ClinicStatusPrompt::class);
        Notification::assertNotSentTo($cancelled, ClinicStatusPrompt::class);
    }

    public function test_inactive_clinicians_are_not_notified(): void
    {
        $clinician = User::factory()->clinician()->inactive()->create();

        ClinicAppointment::factory()
            ->at(now()->addMinutes(62))
            ->create(['assigned_staff_id' => $clinician->id]);

        $this->artisan('clinic:notify-upcoming')->assertSuccessful();

        Notification::assertNotSentTo($clinician, ClinicAppointmentReminder::class);
    }
}
