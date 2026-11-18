<?php

namespace Tests\Feature\Availability;

use App\Enums\LeaveType;
use App\Models\ClinicAppointment;
use App\Models\Event;
use App\Models\GymSchedule;
use App\Models\StaffLeave;
use App\Models\User;
use App\Services\StaffAvailability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Leave as the fourth source in the conflict engine.
 *
 * Two rules matter most here: the window is half-open (back Wednesday means
 * free from Wednesday 00:00), and the labels never name the leave type —
 * clinic bookers read those strings.
 */
class LeaveEngineTest extends TestCase
{
    use RefreshDatabase;

    protected StaffAvailability $availability;

    protected function setUp(): void
    {
        parent::setUp();

        $this->availability = app(StaffAvailability::class);
    }

    protected function leaveFor(User $user, string $first, string $return, LeaveType $type = LeaveType::Annual): StaffLeave
    {
        return StaffLeave::factory()->create([
            'user_id' => $user->id,
            'type' => $type,
            'first_day' => $first,
            'return_date' => $return,
        ]);
    }

    // ---------- conflicts() sees leave ----------

    public function test_conflicts_includes_leave(): void
    {
        $user = User::factory()->create();
        $this->leaveFor($user, '2026-12-07', '2026-12-09');

        $conflicts = $this->availability->conflicts(
            $user->id,
            now()->parse('2026-12-07 09:00'),
            now()->parse('2026-12-07 10:00'),
        );

        $this->assertCount(1, $conflicts);
        $this->assertSame('leave', $conflicts->first()['type']);
    }

    public function test_leave_makes_someone_unavailable(): void
    {
        $user = User::factory()->create();
        $this->leaveFor($user, '2026-12-07', '2026-12-09');

        $this->assertFalse($this->availability->isFree(
            $user->id,
            now()->parse('2026-12-08 09:00'),
            now()->parse('2026-12-08 09:30'),
        ));
    }

    // ---------- half-open ----------

    public function test_the_return_date_itself_is_free(): void
    {
        $user = User::factory()->create();

        // Away Monday and Tuesday, back Wednesday.
        $this->leaveFor($user, '2026-12-07', '2026-12-09');

        $this->assertTrue($this->availability->isFree(
            $user->id,
            now()->parse('2026-12-09 00:00'),
            now()->parse('2026-12-09 01:00'),
        ), 'Back Wednesday must mean free from Wednesday 00:00.');
    }

    public function test_the_last_day_away_is_still_blocked(): void
    {
        $user = User::factory()->create();
        $this->leaveFor($user, '2026-12-07', '2026-12-09');

        $this->assertFalse($this->availability->isFree(
            $user->id,
            now()->parse('2026-12-08 23:00'),
            now()->parse('2026-12-08 23:30'),
        ));
    }

    public function test_the_day_before_the_leave_is_free(): void
    {
        $user = User::factory()->create();
        $this->leaveFor($user, '2026-12-07', '2026-12-09');

        $this->assertTrue($this->availability->isFree(
            $user->id,
            now()->parse('2026-12-06 09:00'),
            now()->parse('2026-12-06 17:00'),
        ));
    }

    public function test_a_window_ending_exactly_at_midnight_on_the_first_day_does_not_clash(): void
    {
        $user = User::factory()->create();
        $this->leaveFor($user, '2026-12-07', '2026-12-09');

        $this->assertTrue($this->availability->isFree(
            $user->id,
            now()->parse('2026-12-06 00:00'),
            now()->parse('2026-12-07 00:00'),
        ));
    }

    // ---------- cancelled leave ----------

    public function test_cancelled_leave_never_blocks(): void
    {
        $user = User::factory()->create();

        StaffLeave::factory()->cancelled()->create([
            'user_id' => $user->id,
            'first_day' => '2026-12-07',
            'return_date' => '2026-12-09',
        ]);

        $this->assertTrue($this->availability->isFree(
            $user->id,
            now()->parse('2026-12-08 09:00'),
            now()->parse('2026-12-08 09:30'),
        ));
    }

    // ---------- labels ----------

    public function test_the_label_names_the_return_date_but_never_the_type(): void
    {
        $user = User::factory()->create();
        $this->leaveFor($user, '2026-12-07', '2026-12-09', LeaveType::Sick);

        $label = $this->availability->conflicts(
            $user->id,
            now()->parse('2026-12-07 09:00'),
            now()->parse('2026-12-07 10:00'),
        )->first()['label'];

        $this->assertSame('On leave (back 9 Dec)', $label);

        foreach (LeaveType::cases() as $type) {
            $this->assertStringNotContainsStringIgnoringCase($type->label(), $label);
            $this->assertStringNotContainsStringIgnoringCase($type->value, $label);
        }
    }

    // ---------- leave-only helpers ----------

    public function test_leave_for_returns_the_overlapping_leave(): void
    {
        $user = User::factory()->create();
        $leave = $this->leaveFor($user, '2026-12-07', '2026-12-09');

        $found = $this->availability->leaveFor(
            $user->id,
            now()->parse('2026-12-08 09:00'),
            now()->parse('2026-12-08 10:00'),
        );

        $this->assertNotNull($found);
        $this->assertSame($leave->id, $found->id);
    }

    public function test_leave_only_helpers_ignore_event_gym_and_clinic_clashes(): void
    {
        $user = User::factory()->clinician()->create();

        // Thoroughly busy, but not on leave.
        $event = Event::factory()->create([
            'starts_at' => now()->parse('2026-12-08 09:00'),
            'ends_at' => now()->parse('2026-12-08 11:00'),
        ]);
        $event->staff()->attach($user);

        $session = GymSchedule::factory()->create([
            'start_date' => '2026-12-08',
            'end_date' => '2026-12-08',
            'start_time' => '09:00',
            'end_time' => '10:00',
            'recurrence' => 'none',
        ]);
        $session->staff()->attach($user);

        ClinicAppointment::factory()->create([
            'assigned_staff_id' => $user->id,
            'starts_at' => now()->parse('2026-12-08 09:00'),
            'ends_at' => now()->parse('2026-12-08 09:30'),
        ]);

        $start = now()->parse('2026-12-08 09:00');
        $end = now()->parse('2026-12-08 09:30');

        $this->assertFalse($this->availability->isFree($user->id, $start, $end), 'sanity: genuinely busy');
        $this->assertNull($this->availability->leaveFor($user->id, $start, $end));
        $this->assertFalse($this->availability->onLeaveMap([$user->id], $start, $end)[$user->id]);
        $this->assertSame([], $this->availability->leaveMap([$user->id], $start, $end));
    }

    public function test_on_leave_map_answers_for_every_id_asked_for(): void
    {
        $away = User::factory()->create();
        $here = User::factory()->create();

        $this->leaveFor($away, '2026-12-07', '2026-12-09');

        $map = $this->availability->onLeaveMap(
            [$away->id, $here->id],
            now()->parse('2026-12-08 09:00'),
            now()->parse('2026-12-08 10:00'),
        );

        $this->assertTrue($map[$away->id]);
        $this->assertFalse($map[$here->id]);
    }

    public function test_leave_map_keys_only_the_people_actually_away(): void
    {
        $away = User::factory()->create();
        $here = User::factory()->create();

        $this->leaveFor($away, '2026-12-07', '2026-12-09');

        $map = $this->availability->leaveMap(
            [$away->id, $here->id],
            now()->parse('2026-12-08 09:00'),
            now()->parse('2026-12-08 10:00'),
        );

        $this->assertArrayHasKey($away->id, $map);
        $this->assertArrayNotHasKey($here->id, $map);
        $this->assertSame('2026-12-09', $map[$away->id]->return_date->toDateString());
    }

    // ---------- the conflict report excludes leave itself ----------

    public function test_assignment_conflicts_excludes_leave_but_keeps_everything_else(): void
    {
        $user = User::factory()->create();
        $this->leaveFor($user, '2026-12-07', '2026-12-09');

        $event = Event::factory()->create([
            'starts_at' => now()->parse('2026-12-08 09:00'),
            'ends_at' => now()->parse('2026-12-08 11:00'),
        ]);
        $event->staff()->attach($user);

        $conflicts = $this->availability->assignmentConflicts(
            $user->id,
            now()->parse('2026-12-07 00:00'),
            now()->parse('2026-12-09 00:00'),
        );

        $this->assertCount(1, $conflicts);
        $this->assertSame('event', $conflicts->first()['type']);
    }
}
