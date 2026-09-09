{{-- Session flash messages, in the system's ink/red vocabulary.
     Success reads green (distinct from the brand red used for errors). --}}
@if (session('status'))
    <div class="rounded-lg border-l-4 border-studio2 bg-studio2-tint px-4 py-3 text-sm font-semibold text-studio2-dark">
        {{ session('status') }}
    </div>
@endif

@if (session('error'))
    <div class="rounded-lg border-l-4 border-accent bg-accent-100 px-4 py-3 text-sm font-semibold text-accent-800">
        {{ session('error') }}
    </div>
@endif
