<?php

namespace Tests\Feature\Availability;

use App\Livewire\Availability\StaffAvailabilityPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Bulk-adding the roster from a pasted list or a CSV.
 *
 * Nothing is written until the admin has seen the preview, and only rows
 * marked New are created.
 */
class RosterImportTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        return User::factory()->admin()->create();
    }

    // ---------- Pasted list ----------

    public function test_a_pasted_list_is_parsed_into_name_position_and_email(): void
    {
        $component = Livewire::actingAs($this->admin())
            ->test(StaffAvailabilityPage::class)
            ->call('openImport')
            ->set('importText', "Kila Vagi, Physiotherapist, kila@example.com\nTom Bani, S&C Coach\nMary Siaka")
            ->call('previewImport');

        $preview = $component->get('importPreview');

        $this->assertCount(3, $preview);

        $this->assertSame('Kila Vagi', $preview[0]['name']);
        $this->assertSame('Physiotherapist', $preview[0]['position']);
        $this->assertSame('kila@example.com', $preview[0]['email']);
        $this->assertSame('new', $preview[0]['status']);

        // Position and email are optional.
        $this->assertSame('S&C Coach', $preview[1]['position']);
        $this->assertNull($preview[1]['email']);
        $this->assertNull($preview[2]['position']);
        $this->assertSame('new', $preview[2]['status']);
    }

    public function test_blank_lines_are_ignored(): void
    {
        $preview = Livewire::actingAs($this->admin())
            ->test(StaffAvailabilityPage::class)
            ->call('openImport')
            ->set('importText', "Kila Vagi\n\n   \nTom Bani\n")
            ->call('previewImport')
            ->get('importPreview');

        $this->assertCount(2, $preview);
    }

    // ---------- Duplicates and invalid rows ----------

    public function test_an_existing_name_is_flagged_as_a_duplicate_case_insensitively(): void
    {
        User::factory()->create(['name' => 'Kila Vagi']);

        $preview = Livewire::actingAs($this->admin())
            ->test(StaffAvailabilityPage::class)
            ->call('openImport')
            ->set('importText', '  kila vagi , Physiotherapist')
            ->call('previewImport')
            ->get('importPreview');

        $this->assertSame('duplicate', $preview[0]['status']);
        $this->assertSame('Name already on the roster', $preview[0]['reason']);
    }

    public function test_an_existing_email_is_flagged_as_a_duplicate(): void
    {
        User::factory()->create(['name' => 'Someone Else', 'email' => 'kila@example.com']);

        $preview = Livewire::actingAs($this->admin())
            ->test(StaffAvailabilityPage::class)
            ->call('openImport')
            ->set('importText', 'Kila Vagi, Physiotherapist, KILA@example.com')
            ->call('previewImport')
            ->get('importPreview');

        $this->assertSame('duplicate', $preview[0]['status']);
        $this->assertSame('Email already in use', $preview[0]['reason']);
    }

    public function test_a_repeat_within_the_same_paste_is_a_duplicate(): void
    {
        $preview = Livewire::actingAs($this->admin())
            ->test(StaffAvailabilityPage::class)
            ->call('openImport')
            ->set('importText', "Kila Vagi\nKila Vagi")
            ->call('previewImport')
            ->get('importPreview');

        $this->assertSame('new', $preview[0]['status']);
        $this->assertSame('duplicate', $preview[1]['status']);
    }

    public function test_a_bad_email_is_invalid(): void
    {
        $preview = Livewire::actingAs($this->admin())
            ->test(StaffAvailabilityPage::class)
            ->call('openImport')
            ->set('importText', 'Kila Vagi, Physiotherapist, not-an-email')
            ->call('previewImport')
            ->get('importPreview');

        $this->assertSame('invalid', $preview[0]['status']);
        $this->assertSame('Invalid email', $preview[0]['reason']);
    }

    public function test_a_missing_name_is_invalid(): void
    {
        $preview = Livewire::actingAs($this->admin())
            ->test(StaffAvailabilityPage::class)
            ->call('openImport')
            ->set('importText', ', Physiotherapist, kila@example.com')
            ->call('previewImport')
            ->get('importPreview');

        $this->assertSame('invalid', $preview[0]['status']);
        $this->assertSame('Missing name', $preview[0]['reason']);
    }

    // ---------- Confirming ----------

    public function test_only_new_rows_are_created_and_all_are_roster_only(): void
    {
        User::factory()->create(['name' => 'Kila Vagi']);

        Livewire::actingAs($this->admin())
            ->test(StaffAvailabilityPage::class)
            ->call('openImport')
            ->set('importText', "Kila Vagi, Physiotherapist\nTom Bani, S&C Coach\n, Nobody\nMary Siaka, Physiotherapist, mary@example.com")
            ->call('previewImport')
            ->call('confirmImport')
            ->assertSet('importResult.created', 2)
            ->assertSet('importResult.duplicates', 1)
            ->assertSet('importResult.invalid', 1);

        $this->assertDatabaseHas('users', [
            'name' => 'Tom Bani',
            'position' => 'S&C Coach',
            'login_enabled' => false,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('users', [
            'name' => 'Mary Siaka',
            'email' => 'mary@example.com',
            'login_enabled' => false,
        ]);

        // The duplicate did not create a second Kila.
        $this->assertSame(1, User::where('name', 'Kila Vagi')->count());
        $this->assertDatabaseMissing('users', ['position' => 'Nobody']);
    }

    public function test_the_preview_is_cleared_after_confirming(): void
    {
        Livewire::actingAs($this->admin())
            ->test(StaffAvailabilityPage::class)
            ->call('openImport')
            ->set('importText', 'Tom Bani')
            ->call('previewImport')
            ->call('confirmImport')
            ->assertSet('importPreview', [])
            ->assertSet('importText', '');
    }

    public function test_an_empty_paste_is_refused(): void
    {
        Livewire::actingAs($this->admin())
            ->test(StaffAvailabilityPage::class)
            ->call('openImport')
            ->set('importText', '   ')
            ->call('previewImport')
            ->assertHasErrors('importText');
    }

    public function test_too_many_rows_are_refused(): void
    {
        $rows = collect(range(1, StaffAvailabilityPage::IMPORT_MAX_ROWS + 1))
            ->map(fn (int $i) => "Person {$i}")
            ->join("\n");

        Livewire::actingAs($this->admin())
            ->test(StaffAvailabilityPage::class)
            ->call('openImport')
            ->set('importText', $rows)
            ->call('previewImport')
            ->assertHasErrors('importText');

        $this->assertSame(1, User::count());
    }

    // ---------- CSV upload ----------

    public function test_a_csv_upload_is_parsed_and_its_header_skipped(): void
    {
        $csv = "name,position,email\nKila Vagi,Physiotherapist,kila@example.com\nTom Bani,S&C Coach,\n";

        $preview = Livewire::actingAs($this->admin())
            ->test(StaffAvailabilityPage::class)
            ->call('openImport')
            ->call('setImportMode', 'file')
            ->set('importFile', UploadedFile::fake()->createWithContent('staff.csv', $csv))
            ->call('previewImport')
            ->get('importPreview');

        $this->assertCount(2, $preview);
        $this->assertSame('Kila Vagi', $preview[0]['name']);
        $this->assertSame('kila@example.com', $preview[0]['email']);
        $this->assertSame('Tom Bani', $preview[1]['name']);
        $this->assertNull($preview[1]['email']);
    }

    public function test_csv_rows_are_classified_like_pasted_rows(): void
    {
        User::factory()->create(['name' => 'Kila Vagi']);

        $csv = "name,position,email\nKila Vagi,Physiotherapist,\nTom Bani,S&C Coach,bad-email\nMary Siaka,,\n";

        Livewire::actingAs($this->admin())
            ->test(StaffAvailabilityPage::class)
            ->call('openImport')
            ->call('setImportMode', 'file')
            ->set('importFile', UploadedFile::fake()->createWithContent('staff.csv', $csv))
            ->call('previewImport')
            ->call('confirmImport')
            ->assertSet('importResult.created', 1)
            ->assertSet('importResult.duplicates', 1)
            ->assertSet('importResult.invalid', 1);

        $this->assertDatabaseHas('users', ['name' => 'Mary Siaka', 'login_enabled' => false]);
    }

    public function test_a_csv_is_required_in_file_mode(): void
    {
        Livewire::actingAs($this->admin())
            ->test(StaffAvailabilityPage::class)
            ->call('openImport')
            ->call('setImportMode', 'file')
            ->call('previewImport')
            ->assertHasErrors('importFile');
    }

    public function test_plain_staff_cannot_import(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(StaffAvailabilityPage::class)
            ->assertForbidden();
    }
}
