<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_upload_profile_picture(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
            'plan' => 'free',
        ]);

        $this->actingAs($user, 'sanctum');

        // Fake the public local storage or public_path
        Storage::fake('public');

        $file = UploadedFile::fake()->create('avatar.jpg', 100, 'image/jpeg');

        $response = $this->postJson('/api/auth/profile/avatar', [
            'avatar' => $file,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'avatar_url',
                'user' => [
                    'id',
                    'name',
                    'email',
                    'avatar_url',
                ]
            ]);

        $user->refresh();
        $this->assertNotNull($user->avatar_url);
        $this->assertStringContainsString('/uploads/avatars/avatar_', $user->avatar_url);

        // Verify the file was stored on disk
        $filename = basename($user->avatar_url);
        $this->assertFileExists(public_path('uploads/avatars/' . $filename));

        // Clean up the created file
        @unlink(public_path('uploads/avatars/' . $filename));
    }

    public function test_cannot_upload_non_image_as_profile_picture(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        $file = UploadedFile::fake()->create('document.pdf', 100);

        $response = $this->postJson('/api/auth/profile/avatar', [
            'avatar' => $file,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['avatar']);
    }
}
