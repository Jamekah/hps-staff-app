<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class InactiveUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_users_cannot_login(): void
    {
        $user = User::factory()->inactive()->create();

        $component = Volt::test('pages.auth.login')
            ->set('form.email', $user->email)
            ->set('form.password', 'password');

        $component->call('login');

        $component->assertHasErrors('form.email');

        $this->assertGuest();
    }

    public function test_deactivated_user_with_existing_session_is_logged_out(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $user->update(['is_active' => false]);

        $response = $this->get('/dashboard');

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_active_users_can_login(): void
    {
        $user = User::factory()->create();

        $component = Volt::test('pages.auth.login')
            ->set('form.email', $user->email)
            ->set('form.password', 'password');

        $component->call('login');

        $component->assertHasNoErrors();

        $this->assertAuthenticated();
    }

    public function test_registration_is_disabled(): void
    {
        $this->get('/register')->assertNotFound();
    }
}
