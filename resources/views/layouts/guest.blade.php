<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=source-sans-3:400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-ink antialiased">
        <div class="safe-top safe-bottom flex min-h-screen flex-col items-center bg-paper px-4 pt-10 sm:justify-center sm:pt-0">
            <div class="flex flex-col items-center">
                <a href="/" wire:navigate>
                    <x-application-logo class="h-20 w-20 object-contain" />
                </a>
                <h1 class="mt-3 text-2xl font-bold tracking-tight text-ink">HPS Operations</h1>
                <p class="hps-label mt-1">High-Performance Sports</p>
            </div>

            <div class="mt-8 w-full rounded-xl border border-ink bg-white p-6 shadow-md sm:max-w-md">
                {{ $slot }}
            </div>

            <x-support-footer />
        </div>
    </body>
</html>
