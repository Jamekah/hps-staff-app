<?php

use App\Livewire\Actions\Logout;
use Livewire\Volt\Component;

new class extends Component
{
    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

<nav x-data="{ open: false }" class="border-b-2 border-ink bg-white">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex h-16 justify-between">
            <div class="flex min-w-0">
                <!-- Logo + app heading -->
                <div class="flex shrink-0 items-center">
                    <a href="{{ route('calendar') }}" wire:navigate class="flex items-center gap-2.5">
                        <x-application-logo class="block h-8 w-auto object-contain" />
                        <span class="whitespace-nowrap text-[15px] font-extrabold tracking-tight text-ink sm:text-[17px]">HPS Operations</span>
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden sm:ms-8 sm:flex sm:items-center sm:gap-6 lg:ms-12 lg:gap-7">
                    <x-nav-link :href="route('calendar')" :active="request()->routeIs('calendar')" wire:navigate>
                        {{ __('Calendar') }}
                    </x-nav-link>

                    <x-nav-link :href="route('gym')" :active="request()->routeIs('gym')" wire:navigate>
                        {{ __('Gym Schedule') }}
                    </x-nav-link>

                    <x-nav-link :href="route('announcements')" :active="request()->routeIs('announcements')" wire:navigate>
                        {{ __('Announcements') }}
                    </x-nav-link>

                    <x-nav-link :href="route('documents.index')" :active="request()->routeIs('documents.*')" wire:navigate>
                        {{ __('Files') }}
                    </x-nav-link>

                    @can('manage-users')
                        <x-nav-link :href="route('users.index')" :active="request()->routeIs('users.*')" wire:navigate>
                            {{ __('Users') }}
                        </x-nav-link>
                    @endcan
                </div>
            </div>

            <!-- Settings Dropdown -->
            <div class="hidden sm:ms-6 sm:flex sm:items-center sm:gap-4">
                <livewire:notifications.notification-bell />

                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center gap-1 text-sm font-semibold text-ink transition hover:text-accent focus:outline-none">
                            <div x-data="{{ json_encode(['name' => auth()->user()->name]) }}" x-text="name" x-on:profile-updated.window="name = $event.detail.name"></div>

                            <svg class="h-4 w-4 fill-current" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile')" wire:navigate>
                            {{ __('Profile') }}
                        </x-dropdown-link>

                        <!-- Authentication -->
                        <button wire:click="logout" class="w-full text-start">
                            <x-dropdown-link>
                                {{ __('Log Out') }}
                            </x-dropdown-link>
                        </button>
                    </x-slot>
                </x-dropdown>
            </div>

            <!-- Hamburger -->
            <div class="-me-2 flex items-center gap-2 sm:hidden">
                <livewire:notifications.notification-bell />
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 text-ink transition hover:bg-ink-100 focus:outline-none" aria-label="Menu">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden border-t-2 border-ink sm:hidden">
        <div class="py-2">
            <x-responsive-nav-link :href="route('calendar')" :active="request()->routeIs('calendar')" wire:navigate>
                {{ __('Calendar') }}
            </x-responsive-nav-link>

            <x-responsive-nav-link :href="route('gym')" :active="request()->routeIs('gym')" wire:navigate>
                {{ __('Gym Schedule') }}
            </x-responsive-nav-link>

            <x-responsive-nav-link :href="route('announcements')" :active="request()->routeIs('announcements')" wire:navigate>
                {{ __('Announcements') }}
            </x-responsive-nav-link>

            <x-responsive-nav-link :href="route('documents.index')" :active="request()->routeIs('documents.*')" wire:navigate>
                {{ __('Files') }}
            </x-responsive-nav-link>

            @can('manage-users')
                <x-responsive-nav-link :href="route('users.index')" :active="request()->routeIs('users.*')" wire:navigate>
                    {{ __('Users') }}
                </x-responsive-nav-link>
            @endcan
        </div>

        <!-- Responsive Settings Options -->
        <div class="border-t border-ink-300 py-3">
            <div class="px-4 pb-2">
                <div class="text-base font-bold text-ink" x-data="{{ json_encode(['name' => auth()->user()->name]) }}" x-text="name" x-on:profile-updated.window="name = $event.detail.name"></div>
                <div class="text-sm text-ink-600">{{ auth()->user()->email }}</div>
            </div>

            <x-responsive-nav-link :href="route('profile')" wire:navigate>
                {{ __('Profile') }}
            </x-responsive-nav-link>

            <!-- Authentication -->
            <button wire:click="logout" class="w-full text-start">
                <x-responsive-nav-link>
                    {{ __('Log Out') }}
                </x-responsive-nav-link>
            </button>
        </div>
    </div>
</nav>
