<?php

namespace App\Livewire\Announcements;

use App\Models\Announcement;
use App\Models\User;
use App\Notifications\AnnouncementPublished;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Notification;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class AnnouncementsPage extends Component
{
    use AuthorizesRequests, WithPagination;

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $title = '';

    public string $body = '';

    public function openCreate(): void
    {
        $this->authorize('create', Announcement::class);

        $this->reset(['editingId', 'title', 'body']);
        $this->resetValidation();
        $this->showForm = true;
    }

    public function openEdit(int $announcementId): void
    {
        $announcement = Announcement::findOrFail($announcementId);
        $this->authorize('update', $announcement);

        $this->editingId = $announcement->id;
        $this->title = $announcement->title;
        $this->body = $announcement->body;
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
        ]);

        if ($this->editingId) {
            $announcement = Announcement::findOrFail($this->editingId);
            $this->authorize('update', $announcement);
            $announcement->update($validated);
            session()->flash('status', 'Announcement updated.');
        } else {
            $this->authorize('create', Announcement::class);
            $this->publish($validated);
            session()->flash('status', 'Announcement published.');
        }

        $this->showForm = false;
    }

    /**
     * Single code path for publishing: creates the announcement and queues
     * the broadcast (in-app + push) to all active users, in chunks so a
     * large staff list never blocks the request.
     */
    protected function publish(array $attributes): Announcement
    {
        $announcement = Announcement::create([...$attributes, 'created_by' => auth()->id()]);

        User::where('is_active', true)
            ->chunkById(100, function ($users) use ($announcement) {
                Notification::send($users, new AnnouncementPublished($announcement));
            });

        return $announcement;
    }

    public function delete(int $announcementId): void
    {
        $announcement = Announcement::findOrFail($announcementId);
        $this->authorize('delete', $announcement);

        $announcement->delete();

        session()->flash('status', 'Announcement deleted.');
    }

    public function render()
    {
        return view('livewire.announcements.announcements-page', [
            'announcements' => Announcement::with('creator')->latest()->paginate(15),
        ]);
    }
}
