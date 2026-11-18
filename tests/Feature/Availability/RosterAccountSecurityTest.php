<?php

namespace Tests\Feature\Availability;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * The security boundary around roster-only accounts.
 *
 * A roster entry is a real row in `users` with a real (random) password hash.
 * It must be impossible to sign in as one, and impossible to have a reset
 * token issued for one — and neither failure may reveal that the account
 * exists.
 */
class RosterAccountSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_roster_only_user_cannot_log_in(): void
    {
        $user = User::factory()->rosterOnly()->create([
            'email' => 'kila@example.com',
            'password' => bcrypt('password'),
        ]);

        Volt::test('pages.auth.login')
            ->set('form.email', 'kila@example.com')
            ->set('form.password', 'password')
            ->call('login')
            ->assertHasErrors('form.email');

        $this->assertGuest();
        $this->assertTrue($user->fresh()->isRosterOnly());
    }

    public function test_the_failure_message_is_the_generic_one(): void
    {
        User::factory()->rosterOnly()->create([
            'email' => 'kila@example.com',
            'password' => bcrypt('password'),
        ]);

        $rosterAttempt = Volt::test('pages.auth.login')
            ->set('form.email', 'kila@example.com')
            ->set('form.password', 'password')
            ->call('login')
            ->errors()
            ->get('form.email');

        // Exactly what a wrong password on a normal account produces: nothing
        // in the message hints that this address belongs to anyone.
        $this->assertSame([__('auth.failed')], $rosterAttempt);
    }

    public function test_no_reset_token_is_issued_for_a_roster_only_user(): void
    {
        Notification::fake();

        $user = User::factory()->rosterOnly()->create(['email' => 'kila@example.com']);

        Volt::test('pages.auth.forgot-password')
            ->set('email', 'kila@example.com')
            ->call('sendPasswordResetLink');

        Notification::assertNothingSentTo($user);
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_the_reset_response_matches_an_unknown_email(): void
    {
        Notification::fake();

        User::factory()->rosterOnly()->create(['email' => 'kila@example.com']);

        $rosterErrors = Volt::test('pages.auth.forgot-password')
            ->set('email', 'kila@example.com')
            ->call('sendPasswordResetLink')
            ->errors()
            ->get('email');

        $unknownErrors = Volt::test('pages.auth.forgot-password')
            ->set('email', 'nobody-at-all@example.com')
            ->call('sendPasswordResetLink')
            ->errors()
            ->get('email');

        $this->assertSame($unknownErrors, $rosterErrors);
    }

    public function test_a_normal_user_can_still_request_a_reset(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'real@example.com']);

        Volt::test('pages.auth.forgot-password')
            ->set('email', 'real@example.com')
            ->call('sendPasswordResetLink');

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_a_live_session_is_dropped_if_the_login_is_turned_off(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/calendar')->assertOk();

        $user->update(['login_enabled' => false]);

        $this->actingAs($user)->get('/calendar')->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
