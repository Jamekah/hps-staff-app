<?php

namespace Tests\Feature\Availability;

use App\Enums\LeaveType;
use App\Models\StaffLeave;
use App\Models\User;
use App\Services\LeaveAllowance;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The allowance service: the single source of truth for every leave figure.
 *
 * Time is frozen throughout — the taken/booked split is defined relative to
 * today, so a test that drifts over midnight would be worthless.
 */
class LeaveAllowanceTest extends TestCase
{
    use RefreshDatabase;

    protected LeaveAllowance $allowance;

    protected function setUp(): void
    {
        parent::setUp();

        // Monday 15 June 2026, mid-year and mid-week.
        Carbon::setTestNow('2026-06-15 09:00:00');

        $this->allowance = app(LeaveAllowance::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    protected function leave(User $user, string $first, string $return, LeaveType $type = LeaveType::Annual): StaffLeave
    {
        return StaffLeave::factory()->create([
            'user_id' => $user->id,
            'type' => $type,
            'first_day' => $first,
            'return_date' => $return,
        ]);
    }

    // ---------- counting ----------

    public function test_it_counts_working_days_only(): void
    {
        $user = User::factory()->create();

        // Mon 2 Mar to Mon 9 Mar: 5 working days across a 7-day span.
        $this->leave($user, '2026-03-02', '2026-03-09');

        $this->assertSame(5, $this->allowance->usedDays($user, 2026, LeaveType::Annual));
    }

    public function test_a_weekend_only_absence_counts_nothing(): void
    {
        $user = User::factory()->create();

        // Sat 20 Jun to Mon 22 Jun.
        $this->leave($user, '2026-06-20', '2026-06-22');

        $this->assertSame(0, $this->allowance->usedDays($user, 2026, LeaveType::Annual));
    }

    public function test_cancelled_leave_is_excluded(): void
    {
        $user = User::factory()->create();

        StaffLeave::factory()->cancelled()->create([
            'user_id' => $user->id,
            'type' => LeaveType::Annual,
            'first_day' => '2026-03-02',
            'return_date' => '2026-03-09',
        ]);

        $this->assertSame(0, $this->allowance->usedDays($user, 2026, LeaveType::Annual));
    }

    public function test_only_the_requested_type_is_counted(): void
    {
        $user = User::factory()->create();

        $this->leave($user, '2026-03-02', '2026-03-09', LeaveType::Annual);
        $this->leave($user, '2026-04-06', '2026-04-09', LeaveType::Sick);

        $this->assertSame(5, $this->allowance->usedDays($user, 2026, LeaveType::Annual));
        $this->assertSame(3, $this->allowance->usedDays($user, 2026, LeaveType::Sick));
        $this->assertSame(8, $this->allowance->usedDays($user, 2026), 'No type means every type.');
    }

    // ---------- the year split ----------

    public function test_a_leave_spanning_new_year_counts_its_own_days_in_each_year(): void
    {
        $user = User::factory()->create();

        // Tue 22 Dec 2026 to Wed 6 Jan 2027: 11 working days in total.
        $this->leave($user, '2026-12-22', '2027-01-06');

        $in2026 = $this->allowance->usedDays($user, 2026, LeaveType::Annual);
        $in2027 = $this->allowance->usedDays($user, 2027, LeaveType::Annual);

        $this->assertSame(8, $in2026);
        $this->assertSame(3, $in2027);
        $this->assertSame(11, $in2026 + $in2027, 'Nothing is lost or double-counted at the boundary.');
    }

    public function test_a_leave_ending_on_new_years_day_does_not_reach_into_the_new_year(): void
    {
        $user = User::factory()->create();

        // Back at work on 1 Jan, so the last day away is 31 Dec.
        $this->leave($user, '2026-12-28', '2027-01-01');

        $this->assertSame(4, $this->allowance->usedDays($user, 2026, LeaveType::Annual));
        $this->assertSame(0, $this->allowance->usedDays($user, 2027, LeaveType::Annual));
    }

    public function test_other_years_are_untouched(): void
    {
        $user = User::factory()->create();

        $this->leave($user, '2025-03-03', '2025-03-10');

        $this->assertSame(0, $this->allowance->usedDays($user, 2026, LeaveType::Annual));
        $this->assertSame(5, $this->allowance->usedDays($user, 2025, LeaveType::Annual));
    }

    // ---------- taken vs booked ----------

    public function test_future_leave_counts_as_used(): void
    {
        $user = User::factory()->create();

        // Entirely after today (15 June).
        $this->leave($user, '2026-08-03', '2026-08-10');

        $this->assertSame(5, $this->allowance->usedDays($user, 2026, LeaveType::Annual));
        $this->assertSame(0, $this->allowance->takenDays($user, 2026, LeaveType::Annual));
        $this->assertSame(5, $this->allowance->bookedDays($user, 2026, LeaveType::Annual));
    }

    public function test_past_leave_counts_as_taken(): void
    {
        $user = User::factory()->create();

        $this->leave($user, '2026-03-02', '2026-03-09');

        $this->assertSame(5, $this->allowance->takenDays($user, 2026, LeaveType::Annual));
        $this->assertSame(0, $this->allowance->bookedDays($user, 2026, LeaveType::Annual));
    }

    public function test_a_leave_running_through_today_splits_at_today(): void
    {
        $user = User::factory()->create();

        // Mon 8 Jun to Mon 22 Jun, with today being Mon 15 Jun:
        // 8–12 June are behind us (5 days), 15–19 June are ahead (5 days).
        $this->leave($user, '2026-06-08', '2026-06-22');

        $this->assertSame(5, $this->allowance->takenDays($user, 2026, LeaveType::Annual));
        $this->assertSame(5, $this->allowance->bookedDays($user, 2026, LeaveType::Annual));
        $this->assertSame(10, $this->allowance->usedDays($user, 2026, LeaveType::Annual));
    }

    public function test_today_itself_counts_as_booked(): void
    {
        $user = User::factory()->create();

        $this->leave($user, '2026-06-15', '2026-06-16');

        $this->assertSame(0, $this->allowance->takenDays($user, 2026, LeaveType::Annual));
        $this->assertSame(1, $this->allowance->bookedDays($user, 2026, LeaveType::Annual));
    }

    public function test_taken_and_booked_always_sum_to_used(): void
    {
        $user = User::factory()->create();

        $this->leave($user, '2026-06-08', '2026-06-22');
        $this->leave($user, '2026-03-02', '2026-03-09');
        $this->leave($user, '2026-09-07', '2026-09-11', LeaveType::Sick);

        foreach ([LeaveType::Annual, LeaveType::Sick, null] as $type) {
            $this->assertSame(
                $this->allowance->usedDays($user, 2026, $type),
                $this->allowance->takenDays($user, 2026, $type) + $this->allowance->bookedDays($user, 2026, $type),
            );
        }
    }

    // ---------- limits ----------

    public function test_remaining_counts_down_from_the_configured_limit(): void
    {
        $user = User::factory()->create();

        $this->leave($user, '2026-03-02', '2026-03-09');

        $this->assertSame(10, $this->allowance->remaining($user, 2026, LeaveType::Annual));
    }

    public function test_remaining_goes_negative_when_over(): void
    {
        $user = User::factory()->create();

        // 20 working days of annual leave against a limit of 15.
        $this->leave($user, '2026-02-02', '2026-03-02');

        $this->assertSame(20, $this->allowance->usedDays($user, 2026, LeaveType::Annual));
        $this->assertSame(-5, $this->allowance->remaining($user, 2026, LeaveType::Annual));
    }

    public function test_unlimited_types_have_no_limit(): void
    {
        $user = User::factory()->create();

        foreach ([LeaveType::Training, LeaveType::DutyTravel, LeaveType::Other] as $type) {
            $this->assertNull($type->allowance());
            $this->assertNull($this->allowance->remaining($user, 2026, $type));
        }
    }

    public function test_limits_come_from_config(): void
    {
        config()->set('leave.allowances.annual', 20);
        config()->set('leave.allowances.sick', 5);

        $this->assertSame(20, LeaveType::Annual->allowance());
        $this->assertSame(5, LeaveType::Sick->allowance());

        $user = User::factory()->create();
        $this->leave($user, '2026-03-02', '2026-03-09');

        $this->assertSame(15, $this->allowance->remaining($user, 2026, LeaveType::Annual));
    }

    public function test_a_type_removed_from_config_becomes_unlimited(): void
    {
        config()->set('leave.allowances', []);

        $this->assertNull(LeaveType::Annual->allowance());
        $this->assertNull($this->allowance->remaining(User::factory()->create(), 2026, LeaveType::Annual));
    }

    // ---------- projections ----------

    public function test_a_projection_reports_the_overage_per_year(): void
    {
        $user = User::factory()->create();

        // 13 of 15 annual already used in 2026.
        $this->leave($user, '2026-02-02', '2026-02-19');
        $this->assertSame(13, $this->allowance->usedDays($user, 2026, LeaveType::Annual));

        // A further 5 working days would make 18.
        $projection = $this->allowance->projectionFor($user->id, LeaveType::Annual, '2026-08-03', '2026-08-10');

        $this->assertSame(13, $projection[2026]['existing']);
        $this->assertSame(5, $projection[2026]['adding']);
        $this->assertSame(18, $projection[2026]['total']);
        $this->assertSame(3, $projection[2026]['over']);

        $this->assertArrayHasKey(2026, $this->allowance->projectedOverage($user->id, LeaveType::Annual, '2026-08-03', '2026-08-10'));
    }

    public function test_a_projection_within_the_limit_reports_no_overage(): void
    {
        $user = User::factory()->create();

        $this->assertSame([], $this->allowance->projectedOverage($user->id, LeaveType::Annual, '2026-08-03', '2026-08-10'));
    }

    public function test_an_unlimited_type_never_projects_an_overage(): void
    {
        $user = User::factory()->create();

        $this->leave($user, '2026-02-02', '2026-03-02', LeaveType::Training);

        $this->assertSame([], $this->allowance->projectedOverage($user->id, LeaveType::Training, '2026-08-03', '2026-09-10'));
    }

    public function test_a_projection_splits_across_new_year(): void
    {
        $user = User::factory()->create();

        $projection = $this->allowance->projectionFor($user->id, LeaveType::Annual, '2026-12-22', '2027-01-06');

        $this->assertSame(8, $projection[2026]['adding']);
        $this->assertSame(3, $projection[2027]['adding']);
    }

    public function test_an_edit_does_not_count_a_leave_against_itself(): void
    {
        $user = User::factory()->create();

        $leave = $this->leave($user, '2026-02-02', '2026-02-19'); // 13 days

        // Without ignoring it, extending it looks like 13 + 14 = 27.
        $naive = $this->allowance->projectionFor($user->id, LeaveType::Annual, '2026-02-02', '2026-02-20');
        $this->assertSame(13, $naive[2026]['existing']);

        // Ignoring it, the same edit is just its own 14 days.
        $corrected = $this->allowance->projectionFor($user->id, LeaveType::Annual, '2026-02-02', '2026-02-20', $leave->id);
        $this->assertSame(0, $corrected[2026]['existing']);
        $this->assertSame(14, $corrected[2026]['total']);
        $this->assertSame(0, $corrected[2026]['over']);
    }

    // ---------- the team summary ----------

    public function test_the_summary_matches_the_per_user_figures(): void
    {
        $one = User::factory()->create(['name' => 'One']);
        $two = User::factory()->create(['name' => 'Two']);

        $this->leave($one, '2026-03-02', '2026-03-09');                        // 5 annual
        $this->leave($one, '2026-04-06', '2026-04-09', LeaveType::Sick);       // 3 sick
        $this->leave($two, '2026-02-02', '2026-03-02');                        // 20 annual

        $summary = $this->allowance->summaryForYear(2026, collect([$one, $two]));

        $this->assertSame(5, $summary['perUser'][$one->id]['byType']['annual']['used']);
        $this->assertSame(3, $summary['perUser'][$one->id]['byType']['sick']['used']);
        $this->assertSame(8, $summary['perUser'][$one->id]['total']);
        $this->assertSame(0, $summary['perUser'][$one->id]['overCount']);

        $this->assertSame(20, $summary['perUser'][$two->id]['byType']['annual']['used']);
        $this->assertSame(5, $summary['perUser'][$two->id]['byType']['annual']['over']);
        $this->assertSame(-5, $summary['perUser'][$two->id]['byType']['annual']['remaining']);
        $this->assertSame(1, $summary['perUser'][$two->id]['overCount']);

        $this->assertSame(25, $summary['teamByType']['annual']);
        $this->assertSame(3, $summary['teamByType']['sick']);
        $this->assertSame(28, $summary['teamTotal']);
        $this->assertSame(1, $summary['staffOverLimit']);
    }

    public function test_the_summary_agrees_with_used_days_for_every_user(): void
    {
        $users = User::factory()->count(5)->create();

        foreach ($users as $index => $user) {
            $this->leave($user, '2026-03-02', '2026-03-0'.(3 + $index));
        }

        $summary = $this->allowance->summaryForYear(2026, $users);

        foreach ($users as $user) {
            $this->assertSame(
                $this->allowance->usedDays($user, 2026, LeaveType::Annual),
                $summary['perUser'][$user->id]['byType']['annual']['used'],
            );
        }
    }

    public function test_the_summary_uses_a_flat_number_of_queries(): void
    {
        // Build both sets up front: only the summary calls themselves are
        // measured, not the factory inserts.
        $few = User::factory()->count(2)->create();
        $many = User::factory()->count(25)->create();

        foreach ($few->merge($many) as $user) {
            $this->leave($user, '2026-03-02', '2026-03-09');
        }

        \DB::enableQueryLog();

        $this->allowance->summaryForYear(2026, $few);
        $withFew = count(\DB::getQueryLog());

        \DB::flushQueryLog();

        $this->allowance->summaryForYear(2026, $many);
        $withMany = count(\DB::getQueryLog());

        \DB::disableQueryLog();

        $this->assertSame($withFew, $withMany, 'Query count must not grow with headcount.');
        $this->assertLessThanOrEqual(2, $withMany);
    }

    public function test_an_empty_user_list_is_handled(): void
    {
        $summary = $this->allowance->summaryForYear(2026, collect());

        $this->assertSame([], $summary['perUser']);
        $this->assertSame(0, $summary['teamTotal']);
        $this->assertSame(0, $summary['staffOverLimit']);
    }

    public function test_the_earliest_year_with_leave_bounds_the_selector(): void
    {
        $this->assertSame(2026, $this->allowance->earliestYearWithLeave(), 'With no leave it is this year.');

        $user = User::factory()->create();
        $this->leave($user, '2023-03-06', '2023-03-13');

        $this->assertSame(2023, $this->allowance->earliestYearWithLeave());
    }
}
