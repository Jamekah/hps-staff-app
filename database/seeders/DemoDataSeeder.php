<?php

namespace Database\Seeders;

use App\Enums\LeaveType;
use App\Enums\Role;
use App\Models\Announcement;
use App\Models\ClinicAppointment;
use App\Models\ClinicClient;
use App\Models\ClinicService;
use App\Models\Event;
use App\Models\GymSchedule;
use App\Models\StaffLeave;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Realistic sample content for reviewing the UI locally.
 *
 * LOCAL ONLY — refuses to run outside the local environment so it can never
 * pollute production data. Run with: php artisan db:seed --class=DemoDataSeeder
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            $this->command?->error('DemoDataSeeder is local-only and will not run in '.app()->environment().'.');

            return;
        }

        $admin = User::updateOrCreate(
            ['email' => 'demo.admin@hps.test'],
            ['name' => 'Demo Admin', 'password' => Hash::make('password'), 'role' => Role::Admin, 'is_active' => true]
        );

        $staff = collect([
            ['Kia Loca', 'demo.kia@hps.test'],
            ['Jayyson Mane', 'demo.jayyson@hps.test'],
            ['Anna Temu', 'demo.anna@hps.test'],
        ])->map(fn (array $person) => User::updateOrCreate(
            ['email' => $person[1]],
            ['name' => $person[0], 'password' => Hash::make('password'), 'role' => Role::Staff, 'is_active' => true]
        ));

        // ---- Events across this month, plus one beyond it for the sidebar ----
        $events = [
            ['Squad Strength Review', 'internal', 'HPS Main Hall', 0, 9, 11, 'Monthly review of squad strength numbers with the coaching group.'],
            ['Sponsor Visit', 'external', 'Taurama Aquatic Centre', 0, 15, 16, 'Walkthrough of the strength hall and recovery rooms, followed by a short Q&A.'],
            ['Coaching Clinic', 'internal', 'Meeting Room 1', 2, 13, 16, 'Practical coaching clinic for junior staff.'],
            ['PNG Games Briefing', 'external', 'Sir John Guise Stadium', 5, 10, 12, 'Pre-games briefing with federation representatives.'],
            ['Recovery Workshop', 'internal', 'HPS Main Hall', 9, 14, 16, ''],
            ['Federation Meeting', 'external', 'Boardroom', 14, 9, 11, 'Quarterly catch-up with national federation leads.'],
        ];

        foreach ($events as [$name, $type, $location, $dayOffset, $startHour, $endHour, $details]) {
            $event = Event::updateOrCreate(
                ['name' => $name],
                [
                    'type' => $type,
                    'location' => $location,
                    'details' => $details ?: null,
                    'starts_at' => today()->addDays($dayOffset)->setTime($startHour, 0),
                    'ends_at' => today()->addDays($dayOffset)->setTime($endHour, 0),
                    'created_by' => $admin->id,
                ]
            );

            $event->staff()->sync($staff->random(rand(1, 3))->pluck('id')->all());
        }

        // A multi-day event, and one in a later month for the "upcoming" panel.
        Event::updateOrCreate(
            ['name' => 'Regional Training Camp'],
            [
                'type' => 'external',
                'location' => 'Lae Aquatic Centre',
                'details' => 'Three-day regional camp.',
                'starts_at' => today()->addDays(4)->setTime(8, 0),
                'ends_at' => today()->addDays(6)->setTime(17, 0),
                'created_by' => $admin->id,
            ]
        )->staff()->sync($staff->pluck('id')->all());

        Event::updateOrCreate(
            ['name' => 'End of Season Review'],
            [
                'type' => 'internal',
                'location' => 'HPS Main Hall',
                'starts_at' => today()->addMonthNoOverflow()->addDays(3)->setTime(10, 0),
                'ends_at' => today()->addMonthNoOverflow()->addDays(3)->setTime(12, 0),
                'created_by' => $admin->id,
            ]
        );

        // ---- Gym sessions: a full day including deliberate overlaps ----
        $sessions = [
            ['Speed & Agility', 'national_federation', 'U18 Squad', '1', '07:00', '08:30'],
            ['Rehab Block', 'external_client', 'K. Loca — individual', '2', '07:00', '08:00'],
            ['Strength', 'national_federation', 'Rugby 7s', '1', '08:30', '10:00'],
            ['Netball Conditioning', 'external_client', 'PNG Netball', '2', '09:00', '10:30'],
            ['Boxing Conditioning', 'external_client', 'PNG Boxing', '1', '09:30', '11:00'],
            ['Junior Camp Circuit', 'national_federation', 'Junior Squad', '1', '11:00', '12:30'],
            ['Physio Screening', 'external_client', 'M. Bani — individual', '2', '11:30', '12:30'],
            ['Staff Training', 'national_federation', 'Internal', '2', '13:00', '14:00'],
            ['Swim Dryland', 'national_federation', 'Swim Squad', '1', '14:00', '15:30'],
            ['Elite Strength', 'national_federation', 'Elite Squad', '1', '16:00', '17:30'],
            ['Team Session', 'national_federation', 'Rugby 7s', '2', '16:30', '18:00'],
        ];

        foreach ($sessions as [$name, $clientType, $clientName, $studio, $start, $end]) {
            $schedule = GymSchedule::updateOrCreate(
                ['name' => $name],
                [
                    'client_type' => $clientType,
                    'client_name' => $clientName,
                    'studio' => $studio,
                    'start_date' => today()->subWeek(),
                    'end_date' => today()->addMonths(2),
                    'start_time' => $start,
                    'end_time' => $end,
                    'recurrence' => 'daily',
                    'created_by' => $admin->id,
                ]
            );

            $schedule->staff()->sync($staff->random(rand(1, 2))->pluck('id')->all());
        }

        // A weekly series, to show the weekday picker in the detail sheet.
        GymSchedule::updateOrCreate(
            ['name' => 'Athletics Track Prep'],
            [
                'client_type' => 'national_federation',
                'client_name' => 'PNG Athletics',
                'studio' => '2',
                'start_date' => today()->subWeek(),
                'end_date' => today()->addMonths(3),
                'start_time' => '15:00',
                'end_time' => '16:30',
                'recurrence' => 'weekly',
                'days_of_week' => [1, 3, 5],
                'created_by' => $admin->id,
            ]
        )->staff()->sync([$staff->first()->id]);

        // ---- Announcements ----
        $announcements = [
            ['Studio 2 closed Friday', "Studio 2 will be closed this Friday for floor resurfacing.\n\nPlease move any booked sessions to Studio 1 and let allocated staff know."],
            ['New recovery protocols', "Updated recovery protocols are now in the Shared Folder.\n\nAll performance staff should read through before next week's sessions."],
            ['Welcome to the team', 'Please welcome our new strength and conditioning staff joining this month.'],
        ];

        foreach ($announcements as $index => [$title, $body]) {
            Announcement::updateOrCreate(
                ['title' => $title],
                ['body' => $body, 'created_by' => $admin->id, 'created_at' => now()->subDays($index * 2)]
            );
        }

        // ---- Phase 6: roster-only staff -------------------------------------
        // Tracked in `users` like everyone else, but with no way in: they show
        // up in every staff picker and in leave, and receive no notifications.
        $roster = collect([
            ['Kila Vagi', 'Physiotherapist'],
            ['Tom Bani', 'S&C Coach'],
            ['Mary Siaka', 'Physiotherapist'],
            ['Peter Namaliu', 'S&C Coach'],
            ['Grace Oala', 'Sports Scientist'],
            ['Joe Marape', 'Team Manager'],
            ['Ruth Kaupa', 'Massage Therapist'],
            ['Simon Aipi', 'Strength Coach'],
            ['Linda Toua', 'Nutritionist'],
            ['Baro Kemo', 'Equipment Officer'],
        ])->map(fn (array $person) => User::updateOrCreate(
            ['name' => $person[0]],
            [
                'position' => $person[1],
                'email' => null,
                'password' => Hash::make(Str::password(32)),
                'role' => Role::Staff,
                'is_active' => true,
                'login_enabled' => false,
            ]
        ));

        // ---- Phase 6: a clinic client and appointments, so leave has
        //      something in the clinic to clash with ---------------------------
        $service = ClinicService::where('name', 'Physiotherapy consultation')->first()
            ?? ClinicService::updateOrCreate(
                ['name' => 'Physiotherapy consultation'],
                ['default_duration_minutes' => 30, 'allows_recurrence' => false, 'is_active' => true]
            );

        $clinician = $staff->first();
        $clinician->update(['is_clinician' => true]);

        $client = ClinicClient::updateOrCreate(
            ['name' => 'Demo Client — J. Hoi'],
            ['organization' => 'PNG Athletics', 'phone' => '+675 7000 0000']
        );

        foreach ([2, 3, 9] as $dayOffset) {
            ClinicAppointment::updateOrCreate(
                [
                    'clinic_client_id' => $client->id,
                    'starts_at' => today()->addDays($dayOffset)->setTime(9, 0),
                ],
                [
                    'clinic_service_id' => $service->id,
                    'assigned_staff_id' => $clinician->id,
                    'booked_by' => $admin->id,
                    'ends_at' => today()->addDays($dayOffset)->setTime(9, 30),
                ]
            );
        }

        // ---- Phase 6 / 6.1: leave -------------------------------------------
        // Covers every state the Overview and the Leave stats panel can show:
        // away today, returning, upcoming, a New Year span, a taken/booked
        // mix, someone at 80% of annual, someone over sick, and people with
        // their whole allowance still intact.
        StaffLeave::query()->delete();

        // Exact working-day spans, so the stats are predictable rather than
        // whatever the calendar happens to give.
        $spanOf = function (Carbon $start, int $workingDays): array {
            $day = $start->copy()->startOfDay();

            while (! $day->isWeekday()) {
                $day->addDay();
            }

            $first = $day->copy();
            $counted = 0;

            while ($counted < $workingDays) {
                if ($day->isWeekday()) {
                    $counted++;
                }

                $day->addDay();
            }

            return [$first->toDateString(), $day->toDateString()];
        };

        $year = now()->year;

        $record = function ($person, LeaveType $type, array $span, ?string $note = null) use ($admin) {
            if (! $person) {
                return;
            }

            StaffLeave::create([
                'user_id' => $person->id,
                'type' => $type,
                'first_day' => $span[0],
                'return_date' => $span[1],
                'note' => $note,
                'recorded_by' => $admin->id,
            ]);
        };

        $clinician = $staff->first();

        // --- Overview states, anchored to today ---
        $record($clinician, LeaveType::Annual, [today()->addDay()->toDateString(), today()->addDays(8)->toDateString()], 'Family trip to Lae.');
        $record($staff->get(1), LeaveType::Sick, [today()->subDays(6)->toDateString(), today()->subDays(3)->toDateString()]);
        $record($staff->get(2), LeaveType::Training, [today()->addDays(12)->toDateString(), today()->addDays(17)->toDateString()], 'World Rugby S&C course.');
        $record($roster->get(0), LeaveType::DutyTravel, [today()->toDateString(), today()->addDays(4)->toDateString()], 'Escorting the swim squad.');
        $record($roster->get(2), LeaveType::Other, [today()->addDays(21)->toDateString(), today()->addDays(23)->toDateString()], 'Bereavement.');

        // --- Allowance states, on fixed dates earlier in the year ---

        // At 80% of annual: 12 of 15 working days used.
        $record($roster->get(4), LeaveType::Annual, $spanOf(Carbon::create($year, 3, 2), 12), 'Long service trip.');

        // Over the sick allowance: 7 + 6 = 13 of 10 working days.
        $record($roster->get(6), LeaveType::Sick, $spanOf(Carbon::create($year, 2, 9), 7), 'Extended illness.');
        $record($roster->get(6), LeaveType::Sick, $spanOf(Carbon::create($year, 5, 11), 6));

        // A taken / booked mix for the same person and type.
        $record($roster->get(1), LeaveType::Annual, $spanOf(Carbon::create($year, 4, 6), 5), 'Taken earlier in the year.');
        $record($roster->get(1), LeaveType::Annual, [today()->addDays(40)->toDateString(), today()->addDays(47)->toDateString()], 'Booked ahead.');

        // Comfortably within both limits.
        $record($roster->get(3), LeaveType::Annual, $spanOf(Carbon::create($year, 6, 1), 4));
        $record($roster->get(3), LeaveType::Sick, $spanOf(Carbon::create($year, 8, 17), 2));

        // Unlimited types, so the plain-number columns have something in them.
        $record($roster->get(5), LeaveType::Training, $spanOf(Carbon::create($year, 7, 6), 5), 'Coaching accreditation.');
        $record($roster->get(8), LeaveType::DutyTravel, $spanOf(Carbon::create($year, 9, 14), 3), 'Regional games.');

        // Spans New Year: each year counts only its own days.
        $record($roster->get(7), LeaveType::Annual, [
            Carbon::create($year, 12, 22)->toDateString(),
            Carbon::create($year + 1, 1, 6)->toDateString(),
        ], 'Christmas and New Year.');

        // Baro Kemo (roster 9) deliberately has none — a full allowance left.

        // One cancelled record, to show that history is kept and never counts.
        StaffLeave::create([
            'user_id' => $roster->get(3)->id,
            'type' => LeaveType::Annual,
            'first_day' => today()->addDays(3)->toDateString(),
            'return_date' => today()->addDays(6)->toDateString(),
            'note' => 'Cancelled — trip called off.',
            'recorded_by' => $admin->id,
            'cancelled_at' => now(),
            'cancelled_by' => $admin->id,
        ]);

                $this->command?->info('Demo content seeded. Log in with your super admin account.');
        $this->command?->info($roster->count().' roster-only staff and '.StaffLeave::count().' leave records added.');
    }
}
