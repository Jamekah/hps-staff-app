<?php

namespace Tests\Feature\Clinic;

use App\Models\ClinicAppointment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Clinic data is confidential. These tests pin down exactly who reaches what.
 */
class ClinicAccessTest extends TestCase
{
    use RefreshDatabase;

    // ---------- Full module ----------

    public function test_super_admin_reaches_the_full_module(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->get('/clinic')->assertOk();
    }

    public function test_admin_reaches_the_full_module(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/clinic')->assertOk();
    }

    public function test_staff_with_booking_privilege_reaches_the_full_module(): void
    {
        $this->actingAs(User::factory()->booker()->create())
            ->get('/clinic')->assertOk();
    }

    // ---------- Plain staff are locked out ----------

    public function test_plain_staff_are_forbidden_from_the_clinic(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/clinic')->assertForbidden();
    }

    public function test_plain_staff_are_forbidden_from_my_appointments(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/clinic/my-appointments')->assertForbidden();
    }

    public function test_plain_staff_see_no_clinic_navigation(): void
    {
        $response = $this->actingAs(User::factory()->create())->get('/calendar');

        $response->assertOk()
            ->assertDontSee('SM Clinic')
            ->assertDontSee('My Appointments');
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/clinic')->assertRedirect(route('login'));
        $this->get('/clinic/my-appointments')->assertRedirect(route('login'));
    }

    // ---------- Clinicians get the scoped view only ----------

    public function test_a_clinician_reaches_only_their_own_appointments(): void
    {
        $clinician = User::factory()->clinician()->create();

        $this->actingAs($clinician)->get('/clinic/my-appointments')->assertOk();
        $this->actingAs($clinician)->get('/clinic')->assertForbidden();
    }

    public function test_a_clinician_sees_my_appointments_but_not_the_full_module_in_nav(): void
    {
        $clinician = User::factory()->clinician()->create();

        $this->actingAs($clinician)->get('/clinic/my-appointments')
            ->assertOk()
            ->assertSee('My Appointments')
            ->assertDontSee('SM Clinic');
    }

    public function test_a_user_holding_assignments_keeps_access_after_the_flag_is_revoked(): void
    {
        $formerClinician = User::factory()->clinician()->create();

        ClinicAppointment::factory()->create(['assigned_staff_id' => $formerClinician->id]);

        // Flag revoked, but existing bookings must not be stranded.
        $formerClinician->update(['is_clinician' => false]);

        $this->actingAs($formerClinician->fresh())
            ->get('/clinic/my-appointments')->assertOk();
    }

    public function test_a_clinician_sees_only_their_own_appointments_listed(): void
    {
        $clinician = User::factory()->clinician()->create();
        $otherClinician = User::factory()->clinician()->create();

        $mine = ClinicAppointment::factory()
            ->at(now()->addDay()->setTime(9, 0))
            ->create(['assigned_staff_id' => $clinician->id]);

        $theirs = ClinicAppointment::factory()
            ->at(now()->addDay()->setTime(11, 0))
            ->create(['assigned_staff_id' => $otherClinician->id]);

        $response = $this->actingAs($clinician)->get('/clinic/my-appointments');

        $response->assertOk()
            ->assertSee($mine->client->name)
            ->assertDontSee($theirs->client->name);
    }

    // ---------- Admin/booker distinction on the users page ----------

    public function test_a_booker_is_not_thereby_a_user_manager(): void
    {
        $this->actingAs(User::factory()->booker()->create())
            ->get('/users')->assertForbidden();
    }
}
