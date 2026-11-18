<?php

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

/**
 * Account activation: a new staff member (or a roster entry that has just been
 * invited) chooses their first password here. Nothing else is asked of them —
 * no email confirmation, no second factor.
 *
 * It is a separate page from the password reset on purpose. The token is read
 * from the `invites` broker, which has its own 24-hour window and its own
 * table, so a setup link and a reset link can never stand in for one another.
 */
new #[Layout('layouts.guest')] class extends Component
{
    #[Locked]
    public string $token = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(string $token): void
    {
        $this->token = $token;

        $this->email = request()->string('email');
    }

    public function setPassword(): void
    {
        $this->validate([
            'token' => ['required'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        $status = Password::broker('invites')->reset(
            $this->only('email', 'password', 'password_confirmation', 'token'),
            function ($user) {
                $user->forceFill([
                    'password' => Hash::make($this->password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status != Password::PASSWORD_RESET) {
            // "Invalid token" reads as an error the staff member caused. For a
            // one-time setup link the real cause is almost always the 24 hours
            // running out, or the link having been used already.
            $this->addError('email', $status === Password::INVALID_TOKEN
                ? __('This setup link has expired or has already been used. Ask your administrator to send you a new one.')
                : __($status));

            return;
        }

        Session::flash('status', __('Your account is ready. Sign in with your new password.'));

        $this->redirectRoute('login', navigate: true);
    }
}; ?>

<div>
    <div class="mb-5">
        <h2 class="text-lg font-semibold text-ink">{{ __('Set up your account') }}</h2>
        <p class="mt-1 text-sm text-ink-700">
            {{ __('Choose a password to activate your account. That is the only step — you will sign in with your email address and this password from now on.') }}
        </p>
    </div>

    <form wire:submit="setPassword">
        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input wire:model="email" id="email" class="block mt-1 w-full" type="email" name="email" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Choose a password')" />
            <x-text-input wire:model="password" id="password" class="block mt-1 w-full" type="password" name="password" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Confirm Password -->
        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="__('Confirm password')" />

            <x-text-input wire:model="password_confirmation" id="password_confirmation" class="block mt-1 w-full"
                          type="password"
                          name="password_confirmation" required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-6">
            <x-primary-button>
                {{ __('Activate account') }}
            </x-primary-button>
        </div>
    </form>
</div>
