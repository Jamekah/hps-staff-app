<?php

namespace Tests\Feature\Auth;

use App\Livewire\Users\ManageUsers;
use App\Models\User;
use App\Notifications\AccountSetupInvitation;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Activation: a new account — and a roster entry that has just been invited —
 * is switched on by its owner choosing a password. One page, 24 hours, no
 * email confirmation and no second factor.
 */
class AccountSetupTest extends TestCase
{
    use RefreshDatabase;

    private function createUserAsSuperAdmin(string $email = 'newstaff@example.com'): User
    {
        $superAdmin = User::factory()->superAdmin()->create();

        Livewire::actingAs($superAdmin)
            ->test(ManageUsers::class)
            ->call('openCreate')
            ->set('name', 'New Staff')
            ->set('email', $email)
            ->set('role', 'staff')
            ->call('save');

        return User::where('email', $email)->firstOrFail();
    }

    private function setupToken(User $user): string
    {
        $token = null;

        Notification::assertSentTo($user, AccountSetupInvitation::class, function ($notification) use (&$token) {
            $token = $notification->token;

            return true;
        });

        return $token;
    }

    public function test_a_new_account_gets_a_setup_email_not_a_reset_email(): void
    {
        Notification::fake();

        $user = $this->createUserAsSuperAdmin();

        Notification::assertSentTo($user, AccountSetupInvitation::class);
        Notification::assertNotSentTo($user, ResetPassword::class);
    }

    public function test_the_setup_email_reads_as_activation_and_names_a_24_hour_window(): void
    {
        Notification::fake();

        $user = $this->createUserAsSuperAdmin();

        Notification::assertSentTo($user, AccountSetupInvitation::class, function ($notification) use ($user) {
            $mail = $notification->toMail($user);
            $lines = array_merge($mail->introLines, $mail->outroLines);

            $this->assertStringContainsString('Set up your', $mail->subject);
            $this->assertStringNotContainsString('reset', strtolower($mail->subject));
            $this->assertSame('Set up your account', $mail->actionText);
            $this->assertStringContainsString('/set-password/', $mail->actionUrl);
            $this->assertNotEmpty(array_filter(
                $lines,
                fn ($line) => str_contains((string) $line, '24 hours'),
            ));

            return true;
        });
    }

    public function test_the_setup_token_lives_in_its_own_table(): void
    {
        Notification::fake();

        $this->createUserAsSuperAdmin();

        $this->assertDatabaseCount('password_invite_tokens', 1);
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_the_setup_page_renders(): void
    {
        Notification::fake();

        $user = $this->createUserAsSuperAdmin();
        $token = $this->setupToken($user);

        // The page is guest-only; the invitee arrives signed out.
        Auth::logout();

        $this->get('/set-password/'.$token.'?email='.urlencode($user->email))
            ->assertSeeVolt('pages.auth.setup-account')
            ->assertStatus(200);
    }

    public function test_a_new_user_activates_the_account_by_setting_a_password(): void
    {
        Notification::fake();

        $user = $this->createUserAsSuperAdmin();
        $token = $this->setupToken($user);

        Volt::test('pages.auth.setup-account', ['token' => $token])
            ->set('email', $user->email)
            ->set('password', 'their-own-password')
            ->set('password_confirmation', 'their-own-password')
            ->call('setPassword')
            ->assertHasNoErrors()
            ->assertRedirect('/login');

        $this->assertTrue(Hash::check('their-own-password', $user->fresh()->password));
        $this->assertDatabaseCount('password_invite_tokens', 0);
    }

    public function test_the_setup_link_still_works_after_23_hours(): void
    {
        Notification::fake();

        $user = $this->createUserAsSuperAdmin();
        $token = $this->setupToken($user);

        $this->travel(23)->hours();

        Volt::test('pages.auth.setup-account', ['token' => $token])
            ->set('email', $user->email)
            ->set('password', 'their-own-password')
            ->set('password_confirmation', 'their-own-password')
            ->call('setPassword')
            ->assertHasNoErrors();

        $this->assertTrue(Hash::check('their-own-password', $user->fresh()->password));
    }

    public function test_the_setup_link_is_dead_after_24_hours(): void
    {
        Notification::fake();

        $user = $this->createUserAsSuperAdmin();
        $token = $this->setupToken($user);
        $originalPassword = $user->password;

        $this->travel(25)->hours();

        $errors = Volt::test('pages.auth.setup-account', ['token' => $token])
            ->set('email', $user->email)
            ->set('password', 'their-own-password')
            ->set('password_confirmation', 'their-own-password')
            ->call('setPassword')
            ->errors()
            ->get('email');

        $this->assertStringContainsString('expired', $errors[0]);
        $this->assertSame($originalPassword, $user->fresh()->password);
    }

    public function test_a_password_reset_token_cannot_be_redeemed_on_the_setup_page(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        Volt::test('pages.auth.forgot-password')
            ->set('email', $user->email)
            ->call('sendPasswordResetLink');

        $resetToken = null;
        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use (&$resetToken) {
            $resetToken = $notification->token;

            return true;
        });

        Volt::test('pages.auth.setup-account', ['token' => $resetToken])
            ->set('email', $user->email)
            ->set('password', 'smuggled-password')
            ->set('password_confirmation', 'smuggled-password')
            ->call('setPassword')
            ->assertHasErrors('email');

        $this->assertFalse(Hash::check('smuggled-password', $user->fresh()->password));
    }

    public function test_a_setup_token_cannot_be_redeemed_on_the_reset_page(): void
    {
        Notification::fake();

        $user = $this->createUserAsSuperAdmin();
        $token = $this->setupToken($user);

        Volt::test('pages.auth.reset-password', ['token' => $token])
            ->set('email', $user->email)
            ->set('password', 'smuggled-password')
            ->set('password_confirmation', 'smuggled-password')
            ->call('resetPassword')
            ->assertHasErrors('email');

        $this->assertFalse(Hash::check('smuggled-password', $user->fresh()->password));
    }

    public function test_a_roster_only_user_is_never_issued_a_setup_token(): void
    {
        Notification::fake();

        $roster = User::factory()->rosterOnly()->create(['email' => 'kila@example.com']);

        // login_enabled guards the credential set itself, not just the UI.
        Password::broker('invites')->sendResetLink(
            ['email' => $roster->email, 'login_enabled' => true],
            fn ($invitee, $token) => $invitee->notify(new AccountSetupInvitation($token)),
        );

        Notification::assertNothingSentTo($roster);
        $this->assertDatabaseCount('password_invite_tokens', 0);
    }
}
