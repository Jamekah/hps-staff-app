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

        // ---- Phase 6: leave, past / current / upcoming -----------------------
        // Deliberately overlapping: $clinician is assigned to events, gym
        // series and clinic appointments above, so every warning state on the
        // Availability page has something real behind it.
        StaffLeave::query()->delete();

        $leaves = [
            // [user, type, first day offset, return offset, note]
            [$clinician, LeaveType::Annual, 1, 8, 'Family trip to Lae.'],
            [$staff->get(1), LeaveType::Sick, -6, -3, null],
            [$staff->get(2), LeaveType::Training, 12, 17, 'World Rugby S&C course.'],
            [$roster->get(0), LeaveType::DutyTravel, 0, 4, 'Escorting the swim squad.'],
            [$roster->get(1), LeaveType::Annual, -30, -20, null],
            [$roster->get(2), LeaveType::Other, 21, 23, 'Bereavement.'],
            [$roster->get(4), LeaveType::Annual, 5, 19, 'Two weeks off.'],
            [$roster->get(6), LeaveType::Sick, 0, 2, null],
        ];

        foreach ($leaves as [$person, $type, $firstOffset, $returnOffset, $note]) {
            if (! $person) {
                continue;
            }

            StaffLeave::create([
                'user_id' => $person->id,
                'type' => $type,
                'first_day' => today()->addDays($firstOffset)->toDateString(),
                'return_date' => today()->addDays($returnOffset)->toDateString(),
                'note' => $note,
                'recorded_by' => $admin->id,
            ]);
        }

        // One cancelled record, to show that history is kept and never blocks.
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
