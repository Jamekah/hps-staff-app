<?php

namespace Tests\Feature\Documents;

use App\Livewire\Documents\SharedFolder;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class SharedFolderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();
    }

    public function test_staff_can_view_the_shared_folder(): void
    {
        $staff = User::factory()->create();
        Document::factory()->create(['title' => 'Staff Handbook']);

        $this->actingAs($staff)
            ->get('/files')
            ->assertOk()
            ->assertSee('Staff Handbook');
    }

    public function test_staff_cannot_upload_or_delete(): void
    {
        $staff = User::factory()->create();
        $document = Document::factory()->create();

        Livewire::actingAs($staff)
            ->test(SharedFolder::class)
            ->call('openUpload')
            ->assertForbidden();

        Livewire::actingAs($staff)
            ->test(SharedFolder::class)
            ->call('delete', $document->id)
            ->assertForbidden();

        $this->assertDatabaseHas('documents', ['id' => $document->id]);
    }

    public function test_admin_can_upload_a_pdf(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(SharedFolder::class)
            ->call('openUpload')
            ->set('title', 'Training Plan')
            ->set('file', UploadedFile::fake()->create('plan.pdf', 500, 'application/pdf'))
            ->call('save')
            ->assertHasNoErrors();

        $document = Document::where('title', 'Training Plan')->first();

        $this->assertNotNull($document);
        $this->assertSame('plan.pdf', $document->original_filename);
        $this->assertSame('application/pdf', $document->mime_type);
        Storage::assertExists($document->storage_path);
        // Stored under a random (non-guessable) name, not the original one.
        $this->assertStringNotContainsString('plan.pdf', $document->storage_path);
    }

    public function test_zip_files_are_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(SharedFolder::class)
            ->call('openUpload')
            ->set('title', 'Sneaky Archive')
            ->set('file', UploadedFile::fake()->create('archive.zip', 500, 'application/zip'))
            ->call('save')
            ->assertHasErrors('file');

        $this->assertDatabaseCount('documents', 0);
    }

    public function test_files_over_20mb_are_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(SharedFolder::class)
            ->call('openUpload')
            ->set('title', 'Huge File')
            ->set('file', UploadedFile::fake()->create('huge.pdf', 25 * 1024, 'application/pdf'))
            ->call('save')
            ->assertHasErrors('file');

        $this->assertDatabaseCount('documents', 0);
    }

    public function test_admin_can_delete_a_document_and_file_is_removed(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(SharedFolder::class)
            ->call('openUpload')
            ->set('title', 'Temp Doc')
            ->set('file', UploadedFile::fake()->create('temp.pdf', 100, 'application/pdf'))
            ->call('save');

        $document = Document::where('title', 'Temp Doc')->first();
        $path = $document->storage_path;

        Livewire::actingAs($admin)
            ->test(SharedFolder::class)
            ->call('delete', $document->id);

        $this->assertDatabaseMissing('documents', ['id' => $document->id]);
        Storage::assertMissing($path);
    }

    public function test_any_authenticated_user_can_download(): void
    {
        $staff = User::factory()->create();
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(SharedFolder::class)
            ->call('openUpload')
            ->set('title', 'Shared Report')
            ->set('file', UploadedFile::fake()->create('report.pdf', 100, 'application/pdf'))
            ->call('save');

        $document = Document::where('title', 'Shared Report')->first();

        $response = $this->actingAs($staff)->get(route('documents.download', $document));

        $response->assertOk();
        $response->assertDownload('report.pdf');
    }

    public function test_guests_cannot_download(): void
    {
        $document = Document::factory()->create();

        $this->get(route('documents.download', $document))
            ->assertRedirect(route('login'));
    }
}
