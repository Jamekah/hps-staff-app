<?php

namespace Tests\Feature\Notifications;

use App\Livewire\Notifications\NotificationBell;
use App\Livewire\Notifications\NotificationsPage;
use App\Models\Announcement;
use App\Models\User;
use App\Notifications\AnnouncementPublished;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class NotificationFeedTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Store an in-app notification for the user. Device tokens never exist in
     * tests, so the FCM channel is a no-op and only the database row lands.
     */
    private function notify(User $user, string $title = 'Test announcement'): void
    {
        $announcement = Announcement::factory()->create(['title' => $title]);

        $user->notify(new AnnouncementPublished($announcement));
    }

    public function test_bell_shows_unread_count(): void
    {
        $user = User::factory()->create();
        $this->notify($user);
        $this->notify($user);

        $component = Livewire::actingAs($user)->test(NotificationBell::class);

        $this->assertSame(2, $component->viewData('unreadCount'));
    }

    public function test_mark_all_read_clears_the_count(): void
    {
        $user = User::factory()->create();
        $this->notify($user);
        $this->notify($user);

        $component = Livewire::actingAs($user)
            ->test(NotificationBell::class)
            ->call('markAllRead');

        $this->assertSame(0, $component->viewData('unreadCount'));
        $this->assertSame(0, $user->fresh()->unreadNotifications()->count());
    }

    public function test_notifications_page_lists_and_marks_individual_as_read(): void
    {
        $user = User::factory()->create();
        $this->notify($user, 'Read me');

        $notification = $user->notifications()->first();

        $this->actingAs($user)->get('/notifications')->assertOk()->assertSee('Read me');

        Livewire::actingAs($user)
            ->test(NotificationsPage::class)
            ->call('markRead', $notification->id);

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_opening_a_notification_marks_it_read_and_redirects_to_its_link(): void
    {
        $user = User::factory()->create();
        $this->notify($user);

        $notification = $user->notifications()->first();

        Livewire::actingAs($user)
            ->test(NotificationBell::class)
            ->call('open', $notification->id)
            ->assertRedirect(route('announcements', absolute: false));

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_users_only_see_their_own_notifications(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->notify($other, 'Not yours');

        $component = Livewire::actingAs($user)->test(NotificationBell::class);

        $this->assertSame(0, $component->viewData('unreadCount'));

        // Attempting to mark another user's notification fails (404) and
        // leaves it unread.
        $notification = $other->notifications()->first();

        try {
            Livewire::actingAs($user)
                ->test(NotificationsPage::class)
                ->call('markRead', $notification->id);

            $this->fail('Expected marking another user\'s notification to fail.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            // Expected: the notification is scoped to the authenticated user.
        }

        $this->assertNull($notification->fresh()->read_at);
    }
}
