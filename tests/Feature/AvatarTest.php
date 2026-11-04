<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AvatarTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_upload_a_cropped_photo_and_serve_it_back(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/avatar', [
            'image_data' => 'data:image/jpeg;base64,'.base64_encode("\xFF\xD8\xFF".'jpeg-bytes'),
        ]);

        $response->assertCreated()->assertJsonPath('avatar_url', '/avatar?v='.$user->fresh()->updated_at->getTimestamp());

        $user = $user->fresh();
        $this->assertSame('avatars/'.$user->id.'.jpg', $user->avatar_path);
        Storage::disk('local')->assertExists($user->avatar_path);

        $this->actingAs($user)->get('/avatar')
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');
    }

    public function test_a_reupload_replaces_the_same_photo(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/avatar', [
            'image_data' => base64_encode("\xFF\xD8\xFF".'first'),
        ])->assertCreated();

        $this->postJson('/api/v1/avatar', [
            'image_data' => base64_encode("\xFF\xD8\xFF".'second'),
        ])->assertCreated();

        $this->assertCount(1, Storage::disk('local')->files('avatars'));
        $this->assertStringContainsString('second', Storage::disk('local')->get($user->fresh()->avatar_path));
    }

    public function test_photo_uploads_reject_data_that_is_not_a_jpeg(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/avatar', [
            'image_data' => base64_encode("\x89PNG".'not-a-jpeg'),
        ])->assertStatus(422)->assertJsonValidationErrors('image');

        $this->postJson('/api/v1/avatar', [])->assertStatus(422);

        $this->assertNull($user->fresh()->avatar_path);
        Storage::disk('local')->assertMissing('avatars/'.$user->id.'.jpg');
    }

    public function test_the_photo_route_is_private(): void
    {
        Storage::fake('local');

        $owner = User::factory()->create();
        Sanctum::actingAs($owner);
        $this->postJson('/api/v1/avatar', [
            'image_data' => base64_encode("\xFF\xD8\xFF".'jpeg-bytes'),
        ])->assertCreated();

        $this->app['auth']->forgetGuards();
        $this->get('/avatar')->assertRedirect(route('login'));

        $other = User::factory()->create();
        $this->actingAs($other)->get('/avatar')->assertNotFound();

        $this->assertStringContainsString('jpeg-bytes', Storage::disk('local')->get($owner->fresh()->avatar_path));
    }

    public function test_an_account_without_a_photo_serialises_a_null_avatar_url(): void
    {
        $user = User::factory()->create();

        $this->assertNull($user->avatar_url);

        Sanctum::actingAs($user);
        $this->getJson('/api/v1/profile')->assertOk()->assertJsonPath('avatar_url', null);
        $this->get('/avatar')->assertNotFound();
    }
}
