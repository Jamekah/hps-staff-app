<?php

namespace Tests\Feature\Clinic;

use App\Enums\AppointmentStatus;
use App\Livewire\Clinic\ClinicSchedule;
use App\Livewire\Clinic\MyAppointments;
use App\Models\ClinicAppointment;
use App\Models\ClinicAppointmentSeries;
use App\Models\ClinicClient;
use App\Models\ClinicService;
use App\Models\User;
use App\Services\StaffAvailability;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ClinicLifecycleTest extends TestCase
{
    use RefreshDatabase;

    // ---------- Cancelling ----------

    public function test_an_admin_can_cancel_an_appointment(): void
    {
        $admin = User::factory()->admin()->create();
        $appointment = ClinicAppointment::factory()->at(now()->addDay()->setTime(9, 0))->create();

        Livewire::actingAs($admin)
            ->test(ClinicSchedule::class)
            ->call('cancelAppointment', $appointment->id);

        $this->assertSame(AppointmentStatus::Cancelled, $appointment->fresh()->status);
    }

    public function test_a_clinician_can_cancel_their_own_appointment(): void
    {
        $clinician = User::factory()->clinician()->create();
        $appointment = ClinicAppointment::factory()
            ->at(now()->addDay()->setTime(9, 0))
            ->create(['assigned_staff_id' => $clinician->id]);

        Livewire::actingAs($clinician)
            ->test(MyAppointments::class)
            ->call('cancelAppointment', $appointment->id);

        $this->assertSame(AppointmentStatus::Cancelled, $appointment->fresh()->status);
    }

    public function test_a_clinician_cannot_act_on_someone_elses_appointment(): void
    {
        $clinician = User::factory()->clinician()->create();
        $othersAppointment = ClinicAppointment::factory()
            ->at(now()->addDay()->setTime(9, 0))
            ->create(['assigned_staff_id' => User::factory()->clinician()->create()->id]);

        // The query is scoped to the signed-in clinician, so another
        // clinician's appointment simply isn't found — it can never be touched.
        try {
            Livewire::actingAs($clinician)
                ->test(MyAppointments::class)
                ->call('cancelAppointment', $othersAppointment->id);

            $this->fail('Expected the appointment to be unreachable.');
        } catch (ModelNotFoundException) {
            // expected
        }

        $this->assertSame(AppointmentStatus::Scheduled, $othersAppointment->fresh()->status);
    }

    public function test_cancelling_frees_the_clinician_for_future_bookings(): void
    {
        $clinician = User::factory()->clinician()->create();
        $availability = app(StaffAvailability::class);

        $appointment = ClinicAppointment::factory()
            ->at(today()->setTime(9, 0), 60)
            ->create(['assigned_staff_id' => $clinician->id]);

        $this->assertFalse($availability->isFree(
            $clinician->id, today()->setTime(9, 30), today()->setTime(10, 0)
        ));

        $appointment->update(['status' => AppointmentStatus::Cancelled]);

        $this->assertTrue($availability->isFree(
            $clinician->id, today()->setTime(9, 30), today()->setTime(10, 0)
        ));
    }

    public function test_a_cancelled_appointment_remains_visible_as_history(): void
    {
        $admin = User::factory()->admin()->create();

        $appointment = ClinicAppointment::factory()
            ->at(today()->setTime(9, 0))
            ->cancelled()
            ->create();

        Livewire::actingAs($admin)
            ->test(ClinicSchedule::class)
            ->assertSee($appointment->client->name);
    }

    public function test_cancelling_a_series_cancels_every_remaining_session(): void
    {
        $admin = User::factory()->admin()->create();
        $clinician = User::factory()->clinician()->create();

        $series = ClinicAppointmentSeries::factory()->create([
            'assigned_staff_id' => $clinician->id,
            'booked_by' => $admin->id,
        ]);

        $appointments = ClinicAppointment::factory()->count(3)->sequence(
            ['starts_at' => today()->addDays(1)->setTime(9, 0), 'ends_at' => today()->addDays(1)->setTime(9, 30)],
            ['starts_at' => today()->addDays(3)->setTime(9, 0), 'ends_at' => today()->addDays(3)->setTime(9, 30)],
            ['starts_at' => today()->addDays(5)->setTime(9, 0), 'ends_at' => today()->addDays(5)->setTime(9, 30)],
        )->create([
            'series_id' => $series->id,
            'assigned_staff_id' => $clinician->id,
        ]);

        Livewire::actingAs($admin)
            ->test(ClinicSchedule::class)
            ->call('cancelSeries', $appointments->first()->id);

        $this->assertSame(
            3,
            ClinicAppointment::where('series_id', $series->id)
                ->where('status', AppointmentStatus::Cancelled)
                ->count()
        );
    }

    public function test_cancelling_one_occurrence_leaves_the_rest_of_the_series(): void
    {
        $admin = User::factory()->admin()->create();
        $series = ClinicAppointmentSeries::factory()->create();

        $appointments = ClinicAppointment::factory()->count(3)->sequence(
            ['starts_at' => today()->addDays(1)->setTime(9, 0), 'ends_at' => today()->addDays(1)->setTime(9, 30)],
            ['starts_at' => today()->addDays(3)->setTime(9, 0), 'ends_at' => today()->addDays(3)->setTime(9, 30)],
            ['starts_at' => today()->addDays(5)->setTime(9, 0), 'ends_at' => today()->addDays(5)->setTime(9, 30)],
        )->create(['series_id' => $series->id]);

        Livewire::actingAs($admin)
            ->test(ClinicSchedule::class)
            ->call('cancelAppointment', $appointments->first()->id);

        $this->assertSame(1, ClinicAppointment::where('series_id', $series->id)
            ->where('status', AppointmentStatus::Cancelled)->count());
        $this->assertSame(2, ClinicAppointment::where('series_id', $series->id)
            ->where('status', AppointmentStatus::Scheduled)->count());
    }

    // ---------- Outcomes ----------

    public function test_the_assigned_clinician_can_record_an_outcome(): void
    {
        $clinician = User::factory()->clinician()->create();

        $appointment = ClinicAppointment::factory()
            ->at(now()->subHours(2))
            ->create(['assigned_staff_id' => $clinician->id]);

        Livewire::actingAs($clinician)
            ->test(MyAppointments::class)
            ->call('setStatus', $appointment->id, 'completed');

        $this->assertSame(AppointmentStatus::Completed, $appointment->fresh()->status);
    }

    public function test_a_clinician_can_record_a_no_show(): void
    {
        $clinician = User::factory()->clinician()->create();

        $appointment = ClinicAppointment::factory()
            ->at(now()->subHours(2))
            ->create(['assigned_staff_id' => $clinician->id]);

        Livewire::actingAs($clinician)
            ->test(MyAppointments::class)
            ->call('setStatus', $appointment->id, 'no_show');

        $this->assertSame(AppointmentStatus::NoShow, $appointment->fresh()->status);
    }

    public function test_an_admin_can_also_record_an_outcome(): void
    {
        $admin = User::factory()->admin()->create();

        $appointment = ClinicAppointment::factory()->at(now()->subHours(2))->create();

        Livewire::actingAs($admin)
            ->test(ClinicSchedule::class)
            ->call('setStatus', $appointment->id, 'completed');

        $this->assertSame(AppointmentStatus::Completed, $appointment->fresh()->status);
    }

    public function test_the_awaiting_filter_surfaces_elapsed_scheduled_appointments(): void
    {
        $clinician = User::factory()->clinician()->create();

        $needsOutcome = ClinicAppointment::factory()
            ->at(now()->subHours(2))
            ->create(['assigned_staff_id' => $clinician->id]);

        $future = ClinicAppointment::factory()
            ->at(now()->addDay())
            ->create(['assigned_staff_id' => $clinician->id]);

        Livewire::actingAs($clinician)
            ->test(MyAppointments::class)
            ->call('setFilter', 'awaiting')
            ->assertSee($needsOutcome->client->name)
            ->assertDontSee($future->client->name);
    }

    public function test_the_status_prompt_deep_link_opens_that_appointment(): void
    {
        $clinician = User::factory()->clinician()->create();

        $appointment = ClinicAppointment::factory()
            ->at(now()->subHours(2))
            ->create(['assigned_staff_id' => $clinician->id]);

        Livewire::actingAs($clinician)
            ->withQueryParams(['appointment' => $appointment->id])
            ->test(MyAppointments::class)
            ->assertSet('selectedAppointmentId', $appointment->id)
            ->assertSet('filter', 'awaiting');
    }

    public function test_a_deep_link_to_someone_elses_appointment_is_ignored(): void
    {
        $clinician = User::factory()->clinician()->create();
        $othersAppointment = ClinicAppointment::factory()
            ->at(now()->subHours(2))
            ->create(['assigned_staff_id' => User::factory()->clinician()->create()->id]);

        Livewire::actingAs($clinician)
            ->withQueryParams(['appointment' => $othersAppointment->id])
            ->test(MyAppointments::class)
            ->assertSet('selectedAppointmentId', null);
    }

    // ---------- Booking through the UI ----------

    public function test_booking_through_the_component_creates_an_appointment(): void
    {
        $admin = User::factory()->admin()->create();
        $clinician = User::factory()->clinician()->create();
        $client = ClinicClient::factory()->create();
        $service = ClinicService::factory()->create(['default_duration_minutes' => 30]);

        Livewire::actingAs($admin)
            ->test(ClinicSchedule::class)
            ->call('openCreate')
            ->set('clinic_client_id', $client->id)
            ->set('clinic_service_id', $service->id)
            ->set('assigned_staff_id', $clinician->id)
            ->set('appointment_date', today()->addDay()->toDateString())
            ->set('start_time', '09:00')
            ->set('end_time', '09:30')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showForm', false);

        $this->assertDatabaseHas('clinic_appointments', [
            'clinic_client_id' => $client->id,
            'assigned_staff_id' => $clinician->id,
            'status' => 'scheduled',
        ]);
    }

    public function test_booking_a_clashing_clinician_is_rejected_with_an_error(): void
    {
        $admin = User::factory()->admin()->create();
        $clinician = User::factory()->clinician()->create();
        $client = ClinicClient::factory()->create();
        $service = ClinicService::factory()->create();

        ClinicAppointment::factory()
            ->at(today()->addDay()->setTime(9, 0), 60)
            ->create(['assigned_staff_id' => $clinician->id]);

        Livewire::actingAs($admin)
            ->test(ClinicSchedule::class)
            ->call('openCreate')
            ->set('clinic_client_id', $client->id)
            ->set('clinic_service_id', $service->id)
            ->set('assigned_staff_id', $clinician->id)
            ->set('appointment_date', today()->addDay()->toDateString())
            ->set('start_time', '09:30')
            ->set('end_time', '10:00')
            ->call('save')
            ->assertHasErrors('assigned_staff_id');

        $this->assertSame(1, ClinicAppointment::count());
    }

    public function test_a_new_client_can_be_created_inline_while_booking(): void
    {
        $admin = User::factory()->admin()->create();
        $clinician = User::factory()->clinician()->create();
        $service = ClinicService::factory()->create();

        Livewire::actingAs($admin)
            ->test(ClinicSchedule::class)
            ->call('openCreate')
            ->call('startNewClient')
            ->set('newClientName', 'Wilma Kaupa')
            ->set('newClientOrganization', 'PNG Athletics')
            ->set('clinic_service_id', $service->id)
            ->set('assigned_staff_id', $clinician->id)
            ->set('appointment_date', today()->addDay()->toDateString())
            ->set('start_time', '10:00')
            ->set('end_time', '10:30')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('clinic_clients', [
            'name' => 'Wilma Kaupa',
            'organization' => 'PNG Athletics',
        ]);
    }

    public function test_a_non_recurring_service_cannot_be_booked_as_a_series(): void
    {
        $admin = User::factory()->admin()->create();
        $clinician = User::factory()->clinician()->create();
        $client = ClinicClient::factory()->create();

        // Sports massage does not allow recurrence.
        $service = ClinicService::factory()->create(['allows_recurrence' => false]);

        Livewire::actingAs($admin)
            ->test(ClinicSchedule::class)
            ->call('openCreate')
            ->set('clinic_client_id', $client->id)
            ->set('clinic_service_id', $service->id)
            ->set('assigned_staff_id', $clinician->id)
            ->set('appointment_date', today()->addDay()->toDateString())
            ->set('start_time', '09:00')
            ->set('end_time', '09:30')
            ->set('isRecurring', true)
            ->set('recurrence', 'weekly')
            ->set('days_of_week', [1])
            ->set('series_end_date', today()->addWeeks(2)->toDateString())
            ->call('save')
            ->assertHasErrors('isRecurring');

        $this->assertSame(0, ClinicAppointment::count());
    }

    public function test_selecting_a_service_prefills_the_end_time_from_its_duration(): void
    {
        $admin = User::factory()->admin()->create();
        $service = ClinicService::factory()->create(['default_duration_minutes' => 45]);

        Livewire::actingAs($admin)
            ->test(ClinicSchedule::class)
            ->call('openCreate')
            ->set('start_time', '09:00')
            ->set('clinic_service_id', $service->id)
            ->assertSet('end_time', '09:45');
    }
}
