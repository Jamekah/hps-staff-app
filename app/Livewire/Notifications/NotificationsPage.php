<?php

namespace App\Livewire\Notifications;

use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class NotificationsPage extends Component
{
    use WithPagination;

    public function markRead(string $notificationId): void
    {
        auth()->user()->notifications()->findOrFail($notificationId)->markAsRead();
    }

    public function markAllRead(): void
    {
        auth()->user()->unreadNotifications->markAsRead();
    }

    public function open(string $notificationId): mixed
    {
        $notification = auth()->user()->notifications()->findOrFail($notificationId);
        $notification->markAsRead();

        return $this->redirect($notification->data['link'] ?? route('notifications'), navigate: true);
    }

    public function render()
    {
        return view('livewire.notifications.notifications-page', [
            'notifications' => auth()->user()->notifications()->latest()->paginate(20),
        ]);
    }
}
