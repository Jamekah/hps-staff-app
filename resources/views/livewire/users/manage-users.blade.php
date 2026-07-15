<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800">User Management</h2>
            <button wire:click="openCreate"
                class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                + New User
            </button>
        </div>

        @if (session('status'))
            <div class="rounded-md bg-green-50 border border-green-200 p-3 text-sm text-green-800">
                {{ session('status') }}
            </div>
        @endif

        @if (session('error'))
            <div class="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-800">
                {{ session('error') }}
            </div>
        @endif

        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-4 border-b border-gray-100">
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search by name or email…"
                    class="w-full sm:w-80 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Name</th>
                            <th class="px-4 py-3">Email</th>
                            <th class="px-4 py-3">Role</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($users as $user)
                            <tr wire:key="user-{{ $user->id }}">
                                <td class="px-4 py-3 font-medium text-gray-900">{{ $user->name }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $user->email }}</td>
                                <td class="px-4 py-3">
                                    <span @class([
                                        'inline-flex rounded-full px-2 py-0.5 text-xs font-semibold',
                                        'bg-purple-100 text-purple-800' => $user->role === \App\Enums\Role::SuperAdmin,
                                        'bg-blue-100 text-blue-800' => $user->role === \App\Enums\Role::Admin,
                                        'bg-gray-100 text-gray-700' => $user->role === \App\Enums\Role::Staff,
                                    ])>
                                        {{ $user->role->label() }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <span @class([
                                        'inline-flex rounded-full px-2 py-0.5 text-xs font-semibold',
                                        'bg-green-100 text-green-800' => $user->is_active,
                                        'bg-red-100 text-red-800' => ! $user->is_active,
                                    ])>
                                        {{ $user->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right space-x-2 whitespace-nowrap">
                                    <button wire:click="openEdit({{ $user->id }})"
                                        class="text-indigo-600 hover:text-indigo-800 font-medium">Edit</button>
                                    <button wire:click="sendResetLink({{ $user->id }})"
                                        class="text-gray-600 hover:text-gray-800 font-medium">Reset link</button>
                                    @if ($user->id !== auth()->id())
                                        <button wire:click="toggleActive({{ $user->id }})"
                                            class="{{ $user->is_active ? 'text-orange-600 hover:text-orange-800' : 'text-green-600 hover:text-green-800' }} font-medium">
                                            {{ $user->is_active ? 'Deactivate' : 'Activate' }}
                                        </button>
                                        <button wire:click="delete({{ $user->id }})"
                                            wire:confirm="Delete {{ $user->name }}? This cannot be undone."
                                            class="text-red-600 hover:text-red-800 font-medium">Delete</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-gray-500">No users found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="p-4">
                {{ $users->links() }}
            </div>
        </div>
    </div>

    {{-- Create / Edit modal --}}
    @if ($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-gray-900/50" wire:click="$set('showModal', false)"></div>

            <div class="relative bg-white rounded-lg shadow-xl w-full max-w-md p-6 space-y-4">
                <h3 class="text-lg font-semibold text-gray-900">
                    {{ $editingId ? 'Edit User' : 'New User' }}
                </h3>

                <form wire:submit="save" class="space-y-4">
                    <div>
                        <x-input-label for="name" value="Name" />
                        <x-text-input wire:model="name" id="name" type="text" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('name')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label for="email" value="Email" />
                        <x-text-input wire:model="email" id="email" type="email" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('email')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label for="role" value="Role" />
                        <select wire:model="role" id="role"
                            class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">
                            @foreach ($roles as $roleOption)
                                <option value="{{ $roleOption->value }}">{{ $roleOption->label() }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('role')" class="mt-1" />
                    </div>

                    @unless ($editingId)
                        <p class="text-xs text-gray-500">
                            The new user will receive an email with a link to set their password.
                        </p>
                    @endunless

                    <div class="flex justify-end gap-3 pt-2">
                        <x-secondary-button wire:click="$set('showModal', false)" type="button">
                            Cancel
                        </x-secondary-button>
                        <x-primary-button>
                            {{ $editingId ? 'Save Changes' : 'Create User' }}
                        </x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
