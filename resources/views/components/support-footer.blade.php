<footer x-data="{ show: false }" class="py-5 text-center">
    <button @click="show = true" type="button"
        class="px-3 py-3.5 text-[11px] font-semibold uppercase tracking-label text-ink-500 transition hover:text-accent">
        Information and support
    </button>

    <div x-show="show" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4"
        role="dialog" aria-modal="true" @keydown.escape.window="show = false">
        <div class="fixed inset-0 bg-ink-900/60" @click="show = false"></div>

        <div class="relative w-full max-w-sm rounded-xl border border-ink bg-white p-6 text-center shadow-lg">
            <img src="{{ asset('images/logo.png') }}" alt="" class="mx-auto h-12 w-auto object-contain" />
            <h3 class="mt-3 text-lg font-bold tracking-tight text-ink">Information &amp; Support</h3>
            <div class="mx-auto my-3 h-0.5 w-10 rounded-full bg-accent"></div>
            <p class="text-sm text-ink-800">
                Developed and maintained by <span class="font-semibold">JMK IT Solutions</span>.
            </p>
            <p class="mt-2 text-sm text-ink-800">
                For support or information visit
                <a href="https://jmkits.net/" target="_blank" rel="noopener"
                    class="font-semibold text-accent-700 underline underline-offset-2 transition hover:text-accent">jmkits.net</a>
            </p>
            <x-secondary-button @click="show = false" class="mt-5">Close</x-secondary-button>
        </div>
    </div>
</footer>
