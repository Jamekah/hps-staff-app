<?php

namespace Tests\Feature\Availability;

use App\Enums\Role;
use App\Livewire\Availability\StaffAvailabilityPage;
use App\Livewire\Users\ManageUsers;
use App\Models\User;
use App\Notifications\AccountSetupInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Who may do what to the staff roster.
 *
 * Admins own the roster — the people who have no login. The super admin keeps
 * everything else: roles, clinic flags, invites, deletion, and any user who
 * can actually sign in.
 */
class RosterManagementTest extends TestCase
{
    use RefreshDatabase;

    // ---------- Admins own roster entries ----------

    public function test_an_admin_can_add_a_roster_only_staff_member(): void
    {
        Notification::fake();

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(StaffAvailabilityPage::class)
            ->call('openStaffForm')
            ->set('staffName', 'Kila Vagi')
            ->set('staffPosition', 'Physiotherapist')
            ->call('saveStaff')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', [
            'name' => 'Kila Vagi',
            'position' => 'Physiotherapist',
            'email' => null,
            'role' => Role::Staff->value,
            'is_active' => true,
            'login_enabled' => false,
        ]);

        // No set-password email: there is no login to set a password for.
        Notification::assertNothingSent();
    }

    public function test_a_new_roster_entry_is_always_plain_staff_without_a_login(): void
    {
        Livewire::actingAs(User::factory()->admin()->create())
            ->test(StaffAvailabilityPage::class)
            ->call('openStaffForm')
            ->set('staffName', 'Tom Bani')
            ->call('saveStaff');

        $user = User::where('name', 'Tom Bani')->first();

        $this->assertSame(Role::Staff, $user->role);
        $this->assertFalse($user->login_enabled);
        $this->assertFalse($user->can_book);
        $this->assertFalse($user->is_clinician);
    }

    public function test_an_admin_can_edit_a_roster_entry(): void
    {
        $roster = User::factory()->rosterOnly()->create(['name' => 'Old Name']);

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(StaffAvailabilityPage::class)
            ->call('openStaffForm', $roster->id)
            ->set('staffName', 'New Name')
            ->set('staffPosition', 'S&C Coach')
            ->set('staffEmail', 'New.Name@Example.com')
            ->call('saveStaff')
            ->assertHasNoErrors();

        $roster->refresh();

        $this->assertSame('New Name', $roster->name);
        $this->assertSame('S&C Coach', $roster->position);
        // Emails are normalised on the way in.
        $this->assertSame('new.name@example.com', $roster->email);
    }

    public function test_an_admin_can_deactivate_and_reactivate_a_roster_entry(): void
    {
        $roster = User::factory()->rosterOnly()->create();
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(StaffAvailabilityPage::class)
            ->call('toggleStaffActive', $roster->id);

        $this->assertFalse($roster->fresh()->is_active);

        Livewire::actingAs($admin)
            ->test(StaffAvailabilityPage::class)
            ->call('toggleStaffActive', $roster->id);

        $this->assertTrue($roster->fresh()->is_active);
    }

    // ---------- …but never a login account ----------

    public function test_an_admin_cannot_edit_a_user_who_has_a_login(): void
    {
        $loginUser = User::factory()->create(['name' => 'Has Login']);

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(StaffAvailabilityPage::class)
            ->call('openStaffForm', $loginUser->id)
            ->assertForbidden();

        $this->assertSame('Has Login', $loginUser->fresh()->name);
    }

    public function test_an_admin_cannot_deactivate_a_user_who_has_a_login(): void
    {
        $loginUser = User::factory()->create();

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(StaffAvailabilityPage::class)
            ->call('toggleStaffActive', $loginUser->id)
            ->assertForbidden();

        $this->assertTrue($loginUser->fresh()->is_active);
    }

    public function test_an_admin_cannot_reach_the_users_page_to_set_roles_or_flags(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/users')
            ->assertForbidden();
    }

    public function test_an_admin_cannot_send_an_invite(): void
    {
        $roster = User::factory()->rosterOnly()->create(['email' => 'kila@example.com']);

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(ManageUsers::class)
            ->assertForbidden();

        $this->assertFalse($roster->fresh()->login_enabled);
    }

    public function test_an_admin_cannot_delete_a_user(): void
    {
        $roster = User::factory()->rosterOnly()->create();
        $admin = User::factory()->admin()->create();

        $this->assertFalse($admin->can('delete', $roster));
    }

    // ---------- Everyone below admin is locked out ----------

    public function test_plain_staff_get_403_from_the_availability_page(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/availability')
            ->assertForbidden();
    }

    public function test_clinic_bookers_get_403_from_the_availability_page(): void
    {
        $this->actingAs(User::factory()->booker()->create())
            ->get('/availability')
            ->assertForbidden();
    }

    public function test_plain_staff_cannot_invoke_roster_actions(): void
    {
        $roster = User::factory()->rosterOnly()->create();

        Livewire::actingAs(User::factory()->create())
            ->test(StaffAvailabilityPage::class)
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $roster->id, 'is_active' => true]);
    }

    // ---------- Send invite ----------

    public function test_the_super_admin_can_invite_a_roster_entry(): void
    {
        Notification::fake();

        $roster = User::factory()->rosterOnly()->create(['email' => 'kila@example.com']);

        Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(ManageUsers::class)
            ->call('sendInvite', $roster->id);

        $roster->refresh();

        $this->assertTrue($roster->login_enabled);
        Notification::assertSentTo($roster, AccountSetupInvitation::class);
    }

    public function test_inviting_someone_without_an_email_asks_for_one_first(): void
    {
        Notification::fake();

        $roster = User::factory()->rosterOnly()->create(['email' => null]);

        Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(ManageUsers::class)
            ->call('sendInvite', $roster->id)
            ->assertSet('showModal', true)
            ->assertSet('editingId', $roster->id);

        $this->assertFalse($roster->fresh()->login_enabled);
        Notification::assertNothingSent();
    }

    public function test_an_invited_user_keeps_their_history(): void
    {
        Notification::fake();

        $roster = User::factory()->rosterOnly()->create([
            'name' => 'Kila Vagi',
            'email' => 'kila@example.com',
            'position' => 'Physiotherapist',
        ]);

        Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(ManageUsers::class)
            ->call('sendInvite', $roster->id);

        // Same row, same id: everything assigned to them is untouched.
        $this->assertDatabaseHas('users', [
            'id' => $roster->id,
            'name' => 'Kila Vagi',
            'position' => 'Physiotherapist',
            'login_enabled' => true,
        ]);
    }

    public function test_a_reset_link_is_refused_for_a_roster_entry(): void
    {
        Notification::fake();

        $roster = User::factory()->rosterOnly()->create(['email' => 'kila@example.com']);

        Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(ManageUsers::class)
            ->call('sendResetLink', $roster->id);

        Notification::assertNothingSentTo($roster);
        $this->assertFalse($roster->fresh()->login_enabled);
    }

    // ---------- Roster staff behave like staff everywhere else ----------

    public function test_roster_staff_appear_in_the_event_and_gym_pickers(): void
    {
        $roster = User::factory()->rosterOnly()->create(['name' => 'Kila Vagi']);
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(\App\Livewire\Calendar\EventsCalendar::class)
            ->call('openCreate')
            ->assertSee('Kila Vagi');

        Livewire::actingAs($admin)
            ->test(\App\Livewire\Gym\GymSchedulePage::class)
            ->call('openCreate')
            ->assertSee('Kila Vagi');

        $this->assertTrue($roster->is_active);
    }
}
