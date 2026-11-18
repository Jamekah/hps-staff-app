<?php

namespace Tests\Feature\Users;

use App\Livewire\Users\ManageUsers;
use App\Models\Announcement;
use App\Models\ClinicAppointment;
use App\Models\DeviceToken;
use App\Models\Document;
use App\Models\Event;
use App\Models\GymSchedule;
use App\Models\StaffLeave;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Deleting a user is permanent — nothing in this app is soft-deleted. What
 * belongs to them cascades away; what they authored restricts the delete, and
 * the super admin is told so in words instead of meeting a 500.
 */
class DeleteUserTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        return User::factory()->superAdmin()->create();
    }

    public function test_deleting_a_plain_staff_member_removes_them_and_everything_assigned_to_them(): void
    {
        $superAdmin = $this->superAdmin();
        $staff = User::factory()->create();

        DeviceToken::create(['user_id' => $staff->id, 'token' => 'tok-1', 'platform' => 'android']);
        Event::factory()->create(['created_by' => $superAdmin->id])->staff()->attach($staff->id);
        StaffLeave::factory()->create(['user_id' => $staff->id, 'recorded_by' => $superAdmin->id]);

        $id = $staff->id;

        Livewire::actingAs($superAdmin)
            ->test(ManageUsers::class)
            ->call('delete', $id);

        $this->assertNull(User::find($id));
        $this->assertSame(0, DB::table('device_tokens')->where('user_id', $id)->count());
        $this->assertSame(0, DB::table('event_staff')->where('user_id', $id)->count());
        $this->assertSame(0, DB::table('staff_leaves')->where('user_id', $id)->count());
    }

    public function test_leave_this_person_recorded_for_someone_else_survives_them(): void
    {
        $superAdmin = $this->superAdmin();
        $recorder = User::factory()->admin()->create();
        $other = User::factory()->create();

        $leave = StaffLeave::factory()->create([
            'user_id' => $other->id,
            'recorded_by' => $recorder->id,
        ]);

        Livewire::actingAs($superAdmin)
            ->test(ManageUsers::class)
            ->call('delete', $recorder->id);

        $this->assertNull(User::find($recorder->id));
        $this->assertDatabaseHas('staff_leaves', ['id' => $leave->id, 'recorded_by' => null]);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function authoredContent(): array
    {
        return [
            'an event' => [Event::class, 'created_by'],
            'a gym schedule' => [GymSchedule::class, 'created_by'],
            'an announcement' => [Announcement::class, 'created_by'],
            'a document' => [Document::class, 'uploaded_by'],
        ];
    }

    #[DataProvider('authoredContent')]
    public function test_someone_who_authored_content_is_refused_with_a_readable_reason(string $model, string $column): void
    {
        $superAdmin = $this->superAdmin();
        $author = User::factory()->admin()->create(['name' => 'Kila Morea']);

        $model::factory()->create([$column => $author->id]);

        Livewire::actingAs($superAdmin)
            ->test(ManageUsers::class)
            ->call('delete', $author->id)
            ->assertSee('Kila Morea cannot be deleted')
            ->assertSee('Deactivate them instead');

        $this->assertNotNull(User::find($author->id), 'The user should survive a refused delete.');
    }

    public function test_a_clinician_holding_appointments_is_refused(): void
    {
        $superAdmin = $this->superAdmin();
        $clinician = User::factory()->clinician()->create();

        ClinicAppointment::factory()->create([
            'assigned_staff_id' => $clinician->id,
            'booked_by' => $superAdmin->id,
        ]);

        Livewire::actingAs($superAdmin)
            ->test(ManageUsers::class)
            ->call('delete', $clinician->id)
            ->assertSee('clinic appointment');

        $this->assertNotNull(User::find($clinician->id));
    }

    public function test_the_reason_names_every_kind_of_content_and_counts_it(): void
    {
        $superAdmin = $this->superAdmin();
        $author = User::factory()->admin()->create();

        Event::factory()->count(3)->create(['created_by' => $author->id]);
        Announcement::factory()->create(['created_by' => $author->id]);

        Livewire::actingAs($superAdmin)
            ->test(ManageUsers::class)
            ->call('delete', $author->id)
            ->assertSee('3 events')
            ->assertSee('1 announcement');
    }

    public function test_a_refused_delete_leaves_the_content_alone(): void
    {
        $superAdmin = $this->superAdmin();
        $author = User::factory()->admin()->create();
        $announcement = Announcement::factory()->create(['created_by' => $author->id]);

        Livewire::actingAs($superAdmin)
            ->test(ManageUsers::class)
            ->call('delete', $author->id);

        $this->assertDatabaseHas('announcements', [
            'id' => $announcement->id,
            'created_by' => $author->id,
        ]);
    }

    public function test_deactivating_is_still_open_to_the_super_admin_when_deleting_is_not(): void
    {
        $superAdmin = $this->superAdmin();
        $author = User::factory()->admin()->create();
        Announcement::factory()->create(['created_by' => $author->id]);

        Livewire::actingAs($superAdmin)
            ->test(ManageUsers::class)
            ->call('delete', $author->id)
            ->call('toggleActive', $author->id);

        $this->assertFalse($author->fresh()->is_active);
    }

    public function test_deletion_blockers_are_empty_for_someone_who_authored_nothing(): void
    {
        $staff = User::factory()->create();

        $this->assertSame([], $staff->deletionBlockers());
    }
}
