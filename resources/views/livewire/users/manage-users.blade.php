<div class="py-4 sm:py-6">
    <div class="mx-auto max-w-7xl space-y-4 px-3 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-xl font-extrabold tracking-tight text-ink sm:text-2xl">User Management</h2>
            <x-primary-button wire:click="openCreate" type="button" class="hidden sm:inline-flex">
                + New User
            </x-primary-button>
        </div>

        <x-flash />

        <div class="hps-panel">
            <div class="border-b border-ink-200 p-4">
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search by name or email…"
                    class="w-full border border-ink-400 bg-surface px-2.5 py-2 text-sm text-ink caret-accent placeholder:text-ink-500 focus:border-accent focus:ring-0 sm:w-80">
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b-2 border-ink">
                            <th class="hps-label px-4 py-3 text-start">Name</th>
                            <th class="hps-label hidden px-4 py-3 text-start sm:table-cell">Email</th>
                            <th class="hps-label px-4 py-3 text-start">Role</th>
                            <th class="hps-label hidden px-4 py-3 text-start sm:table-cell">Status</th>
                            <th class="hps-label px-4 py-3 text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($users as $user)
                            <tr wire:key="user-{{ $user->id }}" class="border-b border-ink-200 transition hover:bg-ink-100">
                                <td class="px-4 py-3">
                                    <div class="font-bold text-ink">{{ $user->name }}</div>
                                    <div class="truncate text-xs text-ink-600 sm:hidden">{{ $user->email }}</div>
                                </td>
                                <td class="hidden px-4 py-3 text-ink-700 sm:table-cell">{{ $user->email }}</td>
                                <td class="px-4 py-3">
                                    <span @class([
                                        'inline-flex px-2 py-1 text-[10px] font-extrabold uppercase tracking-label',
                                        'bg-accent text-white' => $user->role === \App\Enums\Role::SuperAdmin,
                                        'bg-ink-900 text-ink-100' => $user->role === \App\Enums\Role::Admin,
                                        'bg-ink-200 text-ink-800' => $user->role === \App\Enums\Role::Staff,
                                    ])>
                                        {{ $user->role->label() }}
                                    </span>
                                    <span @class([
                                        'mt-1 block text-[10px] font-bold uppercase tracking-label sm:hidden',
                                        'text-studio2' => $user->is_active,
                                        'text-accent-700' => ! $user->is_active,
                                    ])>{{ $user->is_active ? 'Active' : 'Inactive' }}</span>
                                </td>
                                <td class="hidden px-4 py-3 sm:table-cell">
                                    <span @class([
                                        'inline-flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-label',
                                        'text-studio2' => $user->is_active,
                                        'text-accent-700' => ! $user->is_active,
                                    ])>
                                        <span @class([
                                            'h-2 w-2',
                                            'bg-studio2' => $user->is_active,
                                            'bg-accent' => ! $user->is_active,
                                        ])></span>
                                        {{ $user->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-end">
                                    <div class="flex flex-wrap justify-end gap-x-3 gap-y-1">
                                        <button wire:click="openEdit({{ $user->id }})"
                                            class="text-[11px] font-bold uppercase tracking-label text-accent-700 transition hover:text-accent">Edit</button>
                                        <button wire:click="sendResetLink({{ $user->id }})"
                                            class="text-[11px] font-bold uppercase tracking-label text-ink-700 transition hover:text-ink">Reset link</button>
                                        @if ($user->id !== auth()->id())
                                            <button wire:click="toggleActive({{ $user->id }})"
                                                class="text-[11px] font-bold uppercase tracking-label text-ink-700 transition hover:text-ink">
                                                {{ $user->is_active ? 'Deactivate' : 'Activate' }}
                                            </button>
                                            <button wire:click="delete({{ $user->id }})"
                                                wire:confirm="Delete {{ $user->name }}? This cannot be undone."
                                                class="text-[11px] font-bold uppercase tracking-label text-accent-700 transition hover:text-accent">Delete</button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-10 text-center text-sm text-ink-600">No users found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="p-4">
                {{ $users->links() }}
            </div>
        </div>

        <x-primary-button wire:click="openCreate" type="button" class="w-full justify-start py-4 sm:hidden">
            + New User
        </x-primary-button>
    </div>

    {{-- Create / edit form --}}
    @if ($showModal)
        <x-sheet :title="$editingId ? 'Edit User' : 'New User'" close="$set('showModal', false)">
            <form wire:submit="save" class="space-y-4 p-4">
                <div>
                    <x-input-label for="name" value="Name" />
                    <x-text-input wire:model="name" id="name" type="text" class="mt-1" />
                    <x-input-error :messages="$errors->get('name')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="email" value="Email" />
                    <x-text-input wire:model="email" id="email" type="email" class="mt-1" />
                    <x-input-error :messages="$errors->get('email')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="role" value="Role" />
                    <select wire:model="role" id="role"
                        class="mt-1 block w-full border border-ink-400 bg-surface px-2.5 py-1.5 text-sm text-ink focus:border-accent focus:ring-0">
                        @foreach ($roles as $roleOption)
                            <option value="{{ $roleOption->value }}">{{ $roleOption->label() }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('role')" class="mt-1" />
                </div>

                @unless ($editingId)
                    <p class="border-s-4 border-ink bg-ink-100 px-3 py-2 text-xs text-ink-700">
                        The new user will receive an email with a link to set their password.
                    </p>
                @endunless

                <div class="flex justify-end gap-2 pt-1">
                    <x-secondary-button wire:click="$set('showModal', false)" type="button">Cancel</x-secondary-button>
                    <x-primary-button>{{ $editingId ? 'Save Changes' : 'Create User' }}</x-primary-button>
                </div>
            </form>
        </x-sheet>
    @endif
</div>
