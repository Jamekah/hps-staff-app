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

    public string $role = 'staff';

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

        $this->reset(['editingId', 'name', 'email', 'statusMessage']);
        $this->role = 'staff';
        $this->resetValidation();
        $this->showModal = true;
    }

    public function openEdit(int $userId): void
    {
        $this->authorize('manage-users');

        $user = User::findOrFail($userId);

        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->role = $user->role->value;
        $this->statusMessage = null;
        $this->resetValidation();
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->authorize('manage-users');

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'string', 'lowercase', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($this->editingId),
            ],
            'role' => ['required', Rule::in(Role::values())],
        ]);

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
        Password::sendResetLink(['email' => $user->email]);

        session()->flash('status', "Password reset link sent to {$user->email}.");
    }

    public function render()
    {
        $users = User::query()
            ->when($this->search, function ($query) {
                $query->where(fn ($q) => $q
                    ->where('name', 'like', "%{$this->search}%")
                    ->orWhere('email', 'like', "%{$this->search}%"));
            })
            ->orderBy('name')
            ->paginate(15);

        return view('livewire.users.manage-users', [
            'users' => $users,
            'roles' => Role::cases(),
        ]);
    }
}
