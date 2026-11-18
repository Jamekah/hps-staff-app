<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-bold tracking-tight text-ink sm:text-2xl">
            {{ __('Profile') }}
        </h2>
    </x-slot>

    <div class="py-4 sm:py-6">
        <div class="mx-auto max-w-3xl space-y-4 px-3 sm:px-6 lg:px-8">
            <div class="hps-panel p-4 sm:p-6">
                <livewire:profile.update-profile-information-form />
            </div>

            <div class="hps-panel p-4 sm:p-6">
                <livewire:profile.update-password-form />
            </div>

            {{--
                No "Delete account" here. Accounts are the super admin's to
                remove, and an admin may not even delete someone who holds a
                login — self-deletion would have walked straight past both
                rules, permanently and without warning. Leaving is handled by
                the super admin deactivating the account.
            --}}
        </div>
    </div>
</x-app-layout>
