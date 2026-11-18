<?php

namespace App\Livewire\Users;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class ManageUsers extends Component
{
    use AuthorizesRequests, WithPagination;

    public string $search = '';

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $email = '';

    public string $position = '';

    public string $role = 'staff';

    /** Roster entries have no login; the form adapts rather than hiding them. */
    public bool $editingRosterOnly = false;

    /** SM Clinic privileges — orthogonal to the role enum. */
    public bool $can_book = false;

    public bool $is_clinician = false;

    public ?string $statusMessage = null;

    public function mount(): void
    {
        $this->authorize('manage-users');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->authorize('manage-users');

        $this->reset(['editingId', 'name', 'email', 'position', 'statusMessage', 'can_book', 'is_clinician']);
        $this->role = 'staff';
        $this->editingRosterOnly = false;
        $this->resetValidation();
        $this->showModal = true;
    }

    public function openEdit(int $userId): void
    {
        $this->authorize('manage-users');

        $user = User::findOrFail($userId);

        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email ?? '';
        $this->position = $user->position ?? '';
        $this->editingRosterOnly = $user->isRosterOnly();
        $this->role = $user->role->value;
        $this->can_book = $user->can_book;
        $this->is_clinician = $user->is_clinician;
        $this->statusMessage = null;
        $this->resetValidation();
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->authorize('manage-users');

        // A roster entry may legitimately have no email; anyone with a login
        // must have one, since that is how they get in.
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                $this->editingRosterOnly ? 'nullable' : 'required',
                'string', 'lowercase', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($this->editingId),
            ],
            'position' => ['nullable', 'string', 'max:100'],
            'role' => ['required', Rule::in(Role::values())],
            'can_book' => ['boolean'],
            'is_clinician' => ['boolean'],
        ]);

        $validated['email'] = $validated['email'] ?: null;
        $validated['position'] = $validated['position'] ?: null;

        if ($this->editingId) {
            $user = User::findOrFail($this->editingId);

            // Guard: the super admin cannot demote themselves and lock everyone out.
            if ($user->id === auth()->id() && $validated['role'] !== Role::SuperAdmin->value) {
                $this->addError('role', 'You cannot change your own role.');

                return;
            }

            $user->update($validated);
            session()->flash('status', 'User updated.');
        } else {
            $user = User::create([
                ...$validated,
                'password' => Str::password(32),
            ]);

            // New users set their own password via the standard reset-link email.
            Password::sendResetLink(['email' => $user->email]);
            session()->flash('status', 'User created. A set-password link has been emailed to them.');
        }

        $this->showModal = false;
    }

    public function toggleActive(int $userId): void
    {
        $this->authorize('manage-users');

        if ($userId === auth()->id()) {
            session()->flash('error', 'You cannot deactivate your own account.');

            return;
        }

        $user = User::findOrFail($userId);
        $user->update(['is_active' => ! $user->is_active]);

        session()->flash('status', $user->is_active ? "{$user->name} activated." : "{$user->name} deactivated.");
    }

    public function delete(int $userId): void
    {
        $this->authorize('manage-users');

        if ($userId === auth()->id()) {
            session()->flash('error', 'You cannot delete your own account.');

            return;
        }

        $user = User::findOrFail($userId);
        $user->delete();

        session()->flash('status', "{$user->name} deleted.");
    }

    public function sendResetLink(int $userId): void
    {
        $this->authorize('manage-users');

        $user = User::findOrFail($userId);

        if ($user->isRosterOnly()) {
            session()->flash('error', "{$user->name} has no login. Use \"Send invite\" to create one.");

            return;
        }

        // login_enabled is part of the credentials everywhere a reset link is
        // issued, so a roster entry can never be handed a token.
        Password::sendResetLink(['email' => $user->email, 'login_enabled' => true]);

        session()->flash('status', "Password reset link sent to {$user->email}.");
    }

    /**
     * Turn a roster entry into a working login account, keeping its history:
     * the row, and everything assigned to it, stays exactly as it is.
     */
    public function sendInvite(int $userId): void
    {
        $user = User::findOrFail($userId);
        $this->authorize('sendInvite', $user);

        if (! $user->email) {
            session()->flash('error', "Add an email address for {$user->name} first, then send the invite.");

            $this->openEdit($userId);

            return;
        }

        $user->update(['login_enabled' => true]);

        Password::sendResetLink(['email' => $user->email, 'login_enabled' => true]);

        session()->flash('status', "Invite sent to {$user->email}. They can now set a password and sign in.");
    }

    public function render()
    {
        $users = User::query()
            ->when($this->search, function ($query) {
                $query->where(fn ($q) => $q
                    ->where('name', 'like', "%{$this->search}%")
                    ->orWhere('email', 'like', "%{$this->search}%")
                    ->orWhere('position', 'like', "%{$this->search}%"));
            })
            ->orderBy('name')
            ->paginate(15);

        return view('livewire.users.manage-users', [
            'users' => $users,
            'roles' => Role::cases(),
        ]);
    }
}
