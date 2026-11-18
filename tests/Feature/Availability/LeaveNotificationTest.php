<?php

namespace Tests\Feature\Availability;

use App\Livewire\Announcements\AnnouncementsPage;
use App\Models\ClinicAppointment;
use App\Models\Event;
use App\Models\GymSchedule;
use App\Models\StaffLeave;
use App\Models\User;
use App\Notifications\AnnouncementPublished;
use App\Notifications\ClinicAppointmentReminder;
use App\Notifications\EventToday;
use App\Notifications\GymSessionReminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Two rules, applied to every reminder:
 *   - nobody is reminded about something they are on leave for;
 *   - roster-only staff receive nothing at all, since they have no way to
 *     read it and would otherwise find a stale backlog when invited.
 */
class LeaveNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    protected function awayToday(User $user): StaffLeave
    {
        return StaffLeave::factory()->create([
            'user_id' => $user->id,
            'first_day' => today()->toDateString(),
            'return_date' => today()->addDays(3)->toDateString(),
        ]);
    }

    // ---------- events:notify-today ----------

    public function test_the_daily_event_reminder_skips_staff_on_leave(): void
    {
        $away = User::factory()->create();
        $here = User::factory()->create();

        $event = Event::factory()->create([
            'starts_at' => today()->setTime(10, 0),
            'ends_at' => today()->setTime(12, 0),
        ]);
        $event->staff()->attach([$away->id, $here->id]);

        $this->awayToday($away);

        $this->artisan('events:notify-today')->assertSuccessful();

        Notification::assertSentTo($here, EventToday::class);
        Notification::assertNotSentTo($away, EventToday::class);
    }

    public function test_an_event_whose_whole_team_is_away_notifies_nobody(): void
    {
        $away = User::factory()->create();

        $event = Event::factory()->create([
            'starts_at' => today()->setTime(10, 0),
            'ends_at' => today()->setTime(12, 0),
        ]);
        $event->staff()->attach($away);

        $this->awayToday($away);

        $this->artisan('events:notify-today')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_the_daily_event_reminder_skips_roster_only_staff(): void
    {
        $roster = User::factory()->rosterOnly()->create();

        $event = Event::factory()->create([
            'starts_at' => today()->setTime(10, 0),
            'ends_at' => today()->setTime(12, 0),
        ]);
        $event->staff()->attach($roster);

        $this->artisan('events:notify-today')->assertSuccessful();

        Notification::assertNothingSentTo($roster);
    }

    // ---------- gym:notify-upcoming ----------

    public function test_the_gym_reminder_skips_staff_on_leave(): void
    {
        $away = User::factory()->create();
        $here = User::factory()->create();

        $startsAt = now()->addMinutes(62);

        $session = GymSchedule::factory()->create([
            'start_date' => today(),
            'end_date' => today(),
            'start_time' => $startsAt->format('H:i'),
            'end_time' => $startsAt->copy()->addHour()->format('H:i'),
            'recurrence' => 'none',
        ]);
        $session->staff()->attach([$away->id, $here->id]);

        $this->awayToday($away);

        $this->artisan('gym:notify-upcoming')->assertSuccessful();

        Notification::assertSentTo($here, GymSessionReminder::class);
        Notification::assertNotSentTo($away, GymSessionReminder::class);
    }

    public function test_the_gym_reminder_skips_roster_only_staff(): void
    {
        $roster = User::factory()->rosterOnly()->create();

        $startsAt = now()->addMinutes(62);

        $session = GymSchedule::factory()->create([
            'start_date' => today(),
            'end_date' => today(),
            'start_time' => $startsAt->format('H:i'),
            'end_time' => $startsAt->copy()->addHour()->format('H:i'),
            'recurrence' => 'none',
        ]);
        $session->staff()->attach($roster);

        $this->artisan('gym:notify-upcoming')->assertSuccessful();

        Notification::assertNothingSentTo($roster);
    }

    // ---------- clinic:notify-upcoming ----------

    public function test_the_clinic_reminder_skips_a_clinician_on_leave(): void
    {
        $away = User::factory()->clinician()->create();

        ClinicAppointment::factory()->create([
            'assigned_staff_id' => $away->id,
            'starts_at' => now()->addMinutes(62),
            'ends_at' => now()->addMinutes(92),
        ]);

        $this->awayToday($away);

        $this->artisan('clinic:notify-upcoming')->assertSuccessful();

        Notification::assertNotSentTo($away, ClinicAppointmentReminder::class);
    }

    public function test_the_clinic_reminder_still_reaches_a_clinician_who_is_in(): void
    {
        $here = User::factory()->clinician()->create();

        ClinicAppointment::factory()->create([
            'assigned_staff_id' => $here->id,
            'starts_at' => now()->addMinutes(62),
            'ends_at' => now()->addMinutes(92),
        ]);

        $this->artisan('clinic:notify-upcoming')->assertSuccessful();

        Notification::assertSentTo($here, ClinicAppointmentReminder::class);
    }

    // ---------- clinic:prompt-status is deliberately unchanged ----------

    public function test_the_status_prompt_still_fires_for_someone_who_was_on_leave(): void
    {
        $clinician = User::factory()->clinician()->create();

        ClinicAppointment::factory()->create([
            'assigned_staff_id' => $clinician->id,
            'starts_at' => now()->subHours(2),
            'ends_at' => now()->subHour(),
        ]);

        $this->awayToday($clinician);

        $this->artisan('clinic:prompt-status')->assertSuccessful();

        // The outcome still has to be recorded; leave does not excuse it.
        Notification::assertSentTo($clinician, \App\Notifications\ClinicStatusPrompt::class);
    }

    // ---------- announcements ----------

    public function test_an_announcement_never_reaches_roster_only_staff(): void
    {
        $roster = User::factory()->rosterOnly()->create();
        $staff = User::factory()->create();
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(AnnouncementsPage::class)
            ->call('openCreate')
            ->set('title', 'Studio 2 closed Friday')
            ->set('body', 'Floor resurfacing.')
            ->call('save')
            ->assertHasNoErrors();

        Notification::assertSentTo($staff, AnnouncementPublished::class);
        Notification::assertNothingSentTo($roster);
    }

    public function test_an_announcement_still_reaches_staff_who_are_on_leave(): void
    {
        // Leave suppresses *reminders about work*, not general announcements.
        $away = User::factory()->create();
        $this->awayToday($away);

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(AnnouncementsPage::class)
            ->call('openCreate')
            ->set('title', 'New recovery protocols')
            ->set('body', 'In the shared folder.')
            ->call('save');

        Notification::assertSentTo($away, AnnouncementPublished::class);
    }

    public function test_an_inactive_user_still_receives_nothing(): void
    {
        $inactive = User::factory()->inactive()->create();

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(AnnouncementsPage::class)
            ->call('openCreate')
            ->set('title', 'Welcome')
            ->set('body', 'Hello.')
            ->call('save');

        Notification::assertNothingSentTo($inactive);
    }
}
