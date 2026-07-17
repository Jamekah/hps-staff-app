<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen bg-gray-100">
            <livewire:layout.navigation />

            {{-- Dismissible enable-push banner: only shown while permission is undecided --}}
            <div x-data="{ state: window.hpsPushState ? window.hpsPushState() : 'unsupported' }"
                x-show="state === 'default'" x-cloak
                class="bg-indigo-600 text-white text-sm">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-2.5 flex items-center justify-between gap-3">
                    <p>Get notified about events, gym sessions and announcements.</p>
                    <div class="flex items-center gap-2 shrink-0">
                        <button @click="window.hpsEnablePush().then(ok => state = ok ? 'granted' : window.hpsPushState())"
                            class="px-3 py-1 rounded-md bg-white text-indigo-700 text-xs font-semibold hover:bg-indigo-50">
                            Enable notifications
                        </button>
                        <button @click="window.hpsDismissPush(); state = 'dismissed'"
                            class="p-1 text-indigo-200 hover:text-white" aria-label="Dismiss">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Page Heading -->
            @if (isset($header))
                <header class="bg-white shadow">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endif

            <!-- Page Content -->
            <main>
                {{ $slot }}
            </main>
        </div>
    </body>
</html>
