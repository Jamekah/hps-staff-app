<footer x-data="{ show: false }" class="py-4 text-center">
    <button @click="show = true" type="button"
        class="text-xs text-gray-400 hover:text-gray-600 underline underline-offset-2">
        Information and support
    </button>

    <div x-show="show" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4"
        role="dialog" aria-modal="true" @keydown.escape.window="show = false">
        <div class="fixed inset-0 bg-gray-900/50" @click="show = false"></div>

        <div class="relative bg-white rounded-lg shadow-xl w-full max-w-sm p-6 text-center space-y-3">
            <img src="{{ asset('images/logo.png') }}" alt="" class="mx-auto h-12 w-auto object-contain" />
            <h3 class="text-base font-semibold text-gray-900">Information &amp; Support</h3>
            <p class="text-sm text-gray-600">
                Developed and maintained by <span class="font-medium">JMK IT Solutions</span>.
            </p>
            <p class="text-sm text-gray-600">
                For support or information visit
                <a href="https://jmkits.net/" target="_blank" rel="noopener"
                    class="text-indigo-600 hover:text-indigo-800 font-medium">jmkits.net</a>
            </p>
            <button @click="show = false" type="button"
                class="mt-2 inline-flex items-center px-4 py-2 bg-gray-800 rounded-md text-xs font-semibold text-white uppercase tracking-widest hover:bg-gray-700">
                Close
            </button>
        </div>
    </div>
</footer>
