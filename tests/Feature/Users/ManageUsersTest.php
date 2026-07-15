<?php

namespace Tests\Feature\Users;

use App\Enums\Role;
use App\Livewire\Users\ManageUsers;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class ManageUsersTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_cannot_access_user_management(): void
    {
        $staff = User::factory()->create();

        $this->actingAs($staff)->get('/users')->assertForbidden();
    }

    public function test_admin_cannot_access_user_management(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/users')->assertForbidden();
    }

    public function test_super_admin_can_access_user_management(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $this->actingAs($superAdmin)->get('/users')->assertOk();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/users')->assertRedirect(route('login'));
    }

    public function test_super_admin_can_create_a_user_and_reset_link_is_sent(): void
    {
        Notification::fake();

        $superAdmin = User::factory()->superAdmin()->create();

        Livewire::actingAs($superAdmin)
            ->test(ManageUsers::class)
            ->call('openCreate')
            ->set('name', 'New Staff Member')
            ->set('email', 'newstaff@example.com')
            ->set('role', 'staff')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showModal', false);

        $user = User::where('email', 'newstaff@example.com')->first();

        $this->assertNotNull($user);
        $this->assertSame(Role::Staff, $user->role);
        $this->assertTrue($user->is_active);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_super_admin_can_update_a_users_role(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $user = User::factory()->create();

        Livewire::actingAs($superAdmin)
            ->test(ManageUsers::class)
            ->call('openEdit', $user->id)
            ->set('role', 'admin')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(Role::Admin, $user->fresh()->role);
    }

    public function test_super_admin_cannot_change_their_own_role(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        Livewire::actingAs($superAdmin)
            ->test(ManageUsers::class)
            ->call('openEdit', $superAdmin->id)
            ->set('role', 'staff')
            ->call('save')
            ->assertHasErrors('role');

        $this->assertSame(Role::SuperAdmin, $superAdmin->fresh()->role);
    }

    public function test_super_admin_can_deactivate_a_user(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $user = User::factory()->create();

        Livewire::actingAs($superAdmin)
            ->test(ManageUsers::class)
            ->call('toggleActive', $user->id);

        $this->assertFalse($user->fresh()->is_active);
    }

    public function test_super_admin_cannot_deactivate_themselves(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        Livewire::actingAs($superAdmin)
            ->test(ManageUsers::class)
            ->call('toggleActive', $superAdmin->id);

        $this->assertTrue($superAdmin->fresh()->is_active);
    }

    public function test_super_admin_can_delete_a_user(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $user = User::factory()->create();

        Livewire::actingAs($superAdmin)
            ->test(ManageUsers::class)
            ->call('delete', $user->id);

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_super_admin_cannot_delete_themselves(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        Livewire::actingAs($superAdmin)
            ->test(ManageUsers::class)
            ->call('delete', $superAdmin->id);

        $this->assertDatabaseHas('users', ['id' => $superAdmin->id]);
    }

    public function test_staff_cannot_invoke_user_management_actions(): void
    {
        $staff = User::factory()->create();
        $victim = User::factory()->create();

        Livewire::actingAs($staff)
            ->test(ManageUsers::class)
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $victim->id]);
    }
}
