<?php

namespace Tests\Feature\Notifications;

use App\Livewire\Announcements\AnnouncementsPage;
use App\Models\Event;
use App\Models\GymSchedule;
use App\Models\User;
use App\Notifications\AnnouncementPublished;
use App\Notifications\EventToday;
use App\Notifications\GymSessionReminder;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class NotificationTargetingTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    // ---- Event-today (08:00 daily) ----

    public function test_event_today_notifies_assigned_active_staff_only(): void
    {
        Notification::fake();

        $assigned = User::factory()->create();
        $assignedInactive = User::factory()->inactive()->create();
        $unassigned = User::factory()->create();

        $event = Event::factory()->create([
            'starts_at' => today()->setTime(14, 0),
            'ends_at' => today()->setTime(16, 0),
        ]);
        $event->staff()->sync([$assigned->id, $assignedInactive->id]);

        $this->artisan('events:notify-today')->assertSuccessful();

        Notification::assertSentTo($assigned, EventToday::class);
        Notification::assertNotSentTo($assignedInactive, EventToday::class);
        Notification::assertNotSentTo($unassigned, EventToday::class);
    }

    public function test_event_on_another_day_does_not_notify(): void
    {
        Notification::fake();

        $staff = User::factory()->create();

        $event = Event::factory()->create([
            'starts_at' => today()->addDays(2)->setTime(9, 0),
            'ends_at' => today()->addDays(2)->setTime(11, 0),
        ]);
        $event->staff()->sync([$staff->id]);

        $this->artisan('events:notify-today')->assertSuccessful();

        Notification::assertNothingSent();
    }

    // ---- Gym session reminder (every 5 minutes, 60-minute lead) ----

    private function gymSessionStartingInMinutes(int $minutes, User $staff): GymSchedule
    {
        $start = now()->addMinutes($minutes);

        $session = GymSchedule::factory()->create([
            'start_date' => $start->toDateString(),
            'end_date' => $start->toDateString(),
            'start_time' => $start->format('H:i:s'),
            'end_time' => $start->copy()->addHour()->format('H:i:s'),
        ]);
        $session->staff()->sync([$staff->id]);

        return $session;
    }

    public function test_session_62_minutes_ahead_is_caught(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-05 09:00:00'));
        Notification::fake();

        $staff = User::factory()->create();
        $this->gymSessionStartingInMinutes(62, $staff);

        $this->artisan('gym:notify-upcoming')->assertSuccessful();

        Notification::assertSentTo($staff, GymSessionReminder::class);
    }

    public function test_sessions_outside_the_window_are_not_caught(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-05 09:00:00'));
        Notification::fake();

        $staff = User::factory()->create();
        $this->gymSessionStartingInMinutes(30, $staff);
        $this->gymSessionStartingInMinutes(70, $staff);

        $this->artisan('gym:notify-upcoming')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_weekly_recurring_occurrence_today_is_caught(): void
    {
        // 2026-08-05 is a Wednesday (dayOfWeek 3).
        Carbon::setTestNow(Carbon::parse('2026-08-05 09:00:00'));
        Notification::fake();

        $staff = User::factory()->create();

        $session = GymSchedule::factory()
            ->weekly('2026-08-03', '2026-09-30', [3])
            ->create([
                'start_time' => '10:02:00',
                'end_time' => '11:00:00',
            ]);
        $session->staff()->sync([$staff->id]);

        $this->artisan('gym:notify-upcoming')->assertSuccessful();

        Notification::assertSentTo($staff, GymSessionReminder::class);
    }

    public function test_weekly_series_not_occurring_today_is_not_caught(): void
    {
        // Wednesday again, but the series only runs on Mondays (1).
        Carbon::setTestNow(Carbon::parse('2026-08-05 09:00:00'));
        Notification::fake();

        $staff = User::factory()->create();

        $session = GymSchedule::factory()
            ->weekly('2026-08-03', '2026-09-30', [1])
            ->create([
                'start_time' => '10:02:00',
                'end_time' => '11:00:00',
            ]);
        $session->staff()->sync([$staff->id]);

        $this->artisan('gym:notify-upcoming')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_dedupe_prevents_a_second_send_for_the_same_occurrence(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-05 09:00:00'));
        Notification::fake();

        $staff = User::factory()->create();
        $this->gymSessionStartingInMinutes(62, $staff);

        $this->artisan('gym:notify-upcoming')->assertSuccessful();
        $this->artisan('gym:notify-upcoming')->assertSuccessful();

        Notification::assertSentToTimes($staff, GymSessionReminder::class, 1);
    }

    // ---- Announcement broadcast ----

    public function test_announcement_notifies_all_active_users_except_inactive(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();
        $active = User::factory()->create();
        $inactive = User::factory()->inactive()->create();

        Livewire::actingAs($admin)
            ->test(AnnouncementsPage::class)
            ->call('openCreate')
            ->set('title', 'Gym closed Friday')
            ->set('body', 'Maintenance work all day.')
            ->call('save')
            ->assertHasNoErrors();

        Notification::assertSentTo($active, AnnouncementPublished::class);
        Notification::assertSentTo($admin, AnnouncementPublished::class);
        Notification::assertNotSentTo($inactive, AnnouncementPublished::class);
    }

    public function test_editing_an_announcement_does_not_rebroadcast(): void
    {
        $admin = User::factory()->admin()->create();

        $announcement = \App\Models\Announcement::factory()->create(['created_by' => $admin->id]);

        Notification::fake();

        Livewire::actingAs($admin)
            ->test(AnnouncementsPage::class)
            ->call('openEdit', $announcement->id)
            ->set('title', 'Edited title')
            ->call('save')
            ->assertHasNoErrors();

        Notification::assertNothingSent();
    }
}
