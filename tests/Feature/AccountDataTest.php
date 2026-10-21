<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_export_their_account_data(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['sex' => 'female', 'period_status' => 'active'])->save();
        $user->documents()->create([
            'type' => 'lab_result',
            'title' => 'Blood test',
            'original_name' => 'blood-test.pdf',
            'path' => 'documents/'.$user->id.'/blood-test.pdf',
            'mime_type' => 'application/pdf',
            'size' => 100,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/account/export');

        $response->assertOk()
            ->assertHeader('content-disposition')
            ->assertJsonPath('account.email', $user->email)
            ->assertJsonPath('profile.sex', 'female')
            ->assertJsonCount(1, 'documents')
            ->assertJsonPath('documents.0.title', 'Blood test');

        $this->assertStringContainsString('attachment;', $response->headers->get('content-disposition'));
    }

    public function test_deleting_an_account_requires_the_correct_password(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['sex' => 'male'])->save();
        Sanctum::actingAs($user);

        $this->deleteJson('/api/v1/account', ['password' => 'wrong-password'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');

        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_deleting_an_account_removes_records_and_private_documents(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $user->forceFill(['sex' => 'male'])->save();

        $path = 'documents/'.$user->id.'/private.pdf';
        Storage::disk('local')->put($path, 'private content');

        $document = Document::create([
            'user_id' => $user->id,
            'type' => 'medical_certificate',
            'title' => 'Certificate',
            'original_name' => 'certificate.pdf',
            'path' => $path,
            'mime_type' => 'application/pdf',
            'size' => 15,
        ]);

        Sanctum::actingAs($user);

        $this->deleteJson('/api/v1/account', ['password' => 'password'])
            ->assertOk()
            ->assertJsonPath('message', 'Account deleted');

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('documents', ['id' => $document->id]);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_the_privacy_notice_is_public(): void
    {
        $this->get('/privacy')->assertOk()->assertSee('Privacy notice');
    }
}
