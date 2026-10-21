<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DocumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_upload_and_download_a_private_document(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $user->forceFill(['sex' => 'male'])->save();
        Sanctum::actingAs($user);

        $file = UploadedFile::fake()->create('report.pdf', 100, 'application/pdf');

        $response = $this->post('/api/v1/documents', [
            'type' => 'lab_result',
            'title' => 'Blood test — October',
            'notes' => 'Fasting sample.',
            'file' => $file,
        ]);

        $response->assertCreated()
            ->assertJsonPath('title', 'Blood test — October')
            ->assertJsonMissingPath('path');

        $document = Document::firstOrFail();
        Storage::disk('local')->assertExists($document->path);

        $this->get('/api/v1/documents/'.$document->id.'/download')
            ->assertOk()
            ->assertDownload('report.pdf');
    }

    public function test_documents_are_private_to_their_owner(): void
    {
        Storage::fake('local');

        $owner = User::factory()->create();
        $owner->forceFill(['sex' => 'male'])->save();
        $other = User::factory()->create();
        $other->forceFill(['sex' => 'male'])->save();

        Sanctum::actingAs($owner);
        $file = UploadedFile::fake()->create('scan.png', 50, 'image/png');
        $this->post('/api/v1/documents', [
            'type' => 'medical_certificate',
            'title' => 'Medical certificate',
            'file' => $file,
        ])->assertCreated();

        $document = Document::firstOrFail();

        Sanctum::actingAs($other);
        $this->getJson('/api/v1/documents')->assertOk()->assertJsonCount(0);
        $this->get('/api/v1/documents/'.$document->id.'/download')->assertNotFound();
        $this->deleteJson('/api/v1/documents/'.$document->id)->assertNotFound();
    }

    public function test_document_uploads_reject_unsupported_files(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $user->forceFill(['sex' => 'male'])->save();
        Sanctum::actingAs($user);

        $file = UploadedFile::fake()->create('script.exe', 10, 'application/x-msdownload');

        $this->post('/api/v1/documents', [
            'type' => 'other',
            'title' => 'Unsafe file',
            'file' => $file,
        ])->assertStatus(422)->assertJsonValidationErrors('file');
    }

    public function test_deleting_a_document_removes_the_private_file(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $user->forceFill(['sex' => 'male'])->save();
        Sanctum::actingAs($user);

        $file = UploadedFile::fake()->create('prescription.pdf', 20, 'application/pdf');
        $this->post('/api/v1/documents', [
            'type' => 'prescription',
            'title' => 'Prescription',
            'file' => $file,
        ])->assertCreated();

        $document = Document::firstOrFail();
        Storage::disk('local')->assertExists($document->path);

        $this->deleteJson('/api/v1/documents/'.$document->id)->assertOk();

        $this->assertDatabaseMissing('documents', ['id' => $document->id]);
        Storage::disk('local')->assertMissing($document->path);
    }
}
