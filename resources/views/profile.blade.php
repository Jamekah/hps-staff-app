<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-extrabold tracking-tight text-ink sm:text-2xl">
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

            <div class="hps-panel p-4 sm:p-6">
                <livewire:profile.delete-user-form />
            </div>
        </div>
    </div>
</x-app-layout>
