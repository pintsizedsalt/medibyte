<?php

namespace Tests\Feature;

use App\Console\Commands\ServeCommand;
use App\Models\User;
use App\Support\UploadEnvironment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UploadEnvironmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_dev_server_pins_a_writable_upload_temp_directory(): void
    {
        $this->assertInstanceOf(ServeCommand::class, Artisan::all()['serve']);

        $overrides = UploadEnvironment::iniOverrides();

        $this->assertDirectoryExists($overrides['upload_tmp_dir']);
        $this->assertDirectoryIsWritable($overrides['upload_tmp_dir']);
        $this->assertSame('64M', $overrides['upload_max_filesize']);
        $this->assertSame('64M', $overrides['post_max_size']);
    }

    public function test_a_request_body_php_could_not_spool_is_explained_instead_of_reported_as_missing(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['sex' => 'male'])->save();
        Sanctum::actingAs($user);

        $dropped = $this->discardedRequest('/api/v1/documents')
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');

        $this->assertStringContainsString('temporary files directory', $dropped->json('errors.file.0'));

        $this->discardedRequest('/api/v1/avatar')
            ->assertStatus(422)
            ->assertJsonValidationErrors('image');
    }

    public function test_a_body_under_the_spool_limit_is_left_alone(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['sex' => 'male'])->save();
        Sanctum::actingAs($user);

        // Base64 inflates by a third, so this keeps the JSON body under the limit.
        $bytes = "\xff\xd8\xff".random_bytes(10_000);

        $this->postJson('/api/v1/documents', [
            'type' => 'other',
            'title' => 'Small scan',
            'file_name' => 'scan.jpg',
            'mime_type' => 'image/jpeg',
            'file_data' => base64_encode($bytes),
        ])->assertCreated();
    }

    public function test_a_folder_the_server_cannot_write_is_reported_instead_of_recorded(): void
    {
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            $this->markTestSkipped('Root ignores directory permissions.');
        }

        $user = User::factory()->create();
        $user->forceFill(['sex' => 'male'])->save();
        Sanctum::actingAs($user);

        $root = storage_path('framework/testing/unwritable-'.Str::random(8));
        mkdir($root.'/avatars', 0775, true);
        chmod($root.'/avatars', 0555);

        $original = config('filesystems.disks.local.root');
        config(['filesystems.disks.local.root' => $root]);
        Storage::forgetDisk('local');

        try {
            $response = $this->postJson('/api/v1/avatar', [
                'image_data' => 'data:image/jpeg;base64,'.base64_encode("\xff\xd8\xff".random_bytes(2048)),
            ])->assertStatus(422)->assertJsonValidationErrors('image');

            $this->assertStringContainsString('writable', $response->json('errors.image.0'));
            $this->assertNull($user->fresh()->avatar_path);
        } finally {
            chmod($root.'/avatars', 0775);
            rmdir($root.'/avatars');
            rmdir($root);

            config(['filesystems.disks.local.root' => $original]);
            Storage::forgetDisk('local');
        }
    }

    private function discardedRequest(string $uri)
    {
        return $this->call(
            'POST',
            $uri,
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'CONTENT_LENGTH' => (string) (3 * 1024 * 1024),
            ],
            ''
        );
    }
}
