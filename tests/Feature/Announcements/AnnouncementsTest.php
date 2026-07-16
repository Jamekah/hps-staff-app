<?php

namespace Tests\Feature\Announcements;

use App\Livewire\Announcements\AnnouncementsPage;
use App\Models\Announcement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AnnouncementsTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_view_announcements(): void
    {
        $staff = User::factory()->create();
        Announcement::factory()->create(['title' => 'Gym closed Friday']);

        $this->actingAs($staff)
            ->get('/announcements')
            ->assertOk()
            ->assertSee('Gym closed Friday');
    }

    public function test_staff_cannot_create_announcements(): void
    {
        $staff = User::factory()->create();

        Livewire::actingAs($staff)
            ->test(AnnouncementsPage::class)
            ->call('openCreate')
            ->assertForbidden();
    }

    public function test_admin_can_publish_an_announcement(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(AnnouncementsPage::class)
            ->call('openCreate')
            ->set('title', 'New opening hours')
            ->set('body', 'From Monday the gym opens at 6am.')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('announcements', [
            'title' => 'New opening hours',
            'created_by' => $admin->id,
        ]);
    }

    public function test_admin_can_edit_their_own_announcement(): void
    {
        $admin = User::factory()->admin()->create();
        $announcement = Announcement::factory()->create(['created_by' => $admin->id]);

        Livewire::actingAs($admin)
            ->test(AnnouncementsPage::class)
            ->call('openEdit', $announcement->id)
            ->set('title', 'Updated title')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Updated title', $announcement->fresh()->title);
    }

    public function test_admin_cannot_edit_another_admins_announcement(): void
    {
        $admin = User::factory()->admin()->create();
        $otherAdmin = User::factory()->admin()->create();
        $announcement = Announcement::factory()->create(['created_by' => $otherAdmin->id]);

        Livewire::actingAs($admin)
            ->test(AnnouncementsPage::class)
            ->call('openEdit', $announcement->id)
            ->assertForbidden();

        Livewire::actingAs($admin)
            ->test(AnnouncementsPage::class)
            ->call('delete', $announcement->id)
            ->assertForbidden();
    }

    public function test_super_admin_can_edit_and_delete_any_announcement(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $admin = User::factory()->admin()->create();
        $announcement = Announcement::factory()->create(['created_by' => $admin->id]);

        Livewire::actingAs($superAdmin)
            ->test(AnnouncementsPage::class)
            ->call('openEdit', $announcement->id)
            ->set('title', 'Corrected by super admin')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Corrected by super admin', $announcement->fresh()->title);

        Livewire::actingAs($superAdmin)
            ->test(AnnouncementsPage::class)
            ->call('delete', $announcement->id);

        $this->assertDatabaseMissing('announcements', ['id' => $announcement->id]);
    }
}
