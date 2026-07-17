<?php

namespace App\Livewire\Notifications;

use Livewire\Component;

class NotificationBell extends Component
{
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
        $user = auth()->user();

        return view('livewire.notifications.notification-bell', [
            'unreadCount' => $user->unreadNotifications()->count(),
            'recent' => $user->notifications()->latest()->limit(10)->get(),
        ]);
    }
}
