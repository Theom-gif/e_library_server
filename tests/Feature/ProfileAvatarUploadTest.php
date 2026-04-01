<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileAvatarUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_upload_avatar_via_dedicated_endpoint(): void
    {
        $user = $this->createAuthenticatedUser();

        $response = $this->post('/api/me/avatar', [
            'avatar' => UploadedFile::fake()->image('avatar.jpg'),
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Avatar uploaded successfully');

        $photoUrl = $response->json('data.photo');

        $this->assertIsString($photoUrl);
        $this->assertStringStartsWith('/avatars/'.$user->id, $photoUrl);
        $this->assertSame($photoUrl, $response->json('data.photo_url'));
        $this->assertSame($photoUrl, $response->json('data.user.photo'));
        $this->assertSame($photoUrl, $response->json('data.user.photo_url'));
        $this->assertDatabaseHas('user_avatars', [
            'user_id' => $user->id,
            'mime_type' => 'image/jpeg',
        ]);
    }

    public function test_user_can_update_profile_with_avatar_via_post_multipart(): void
    {
        $user = $this->createAuthenticatedUser();

        $response = $this->post('/api/me/profile', [
            'firstname' => 'Updated',
            'lastname' => 'Reader',
            'photo' => UploadedFile::fake()->image('profile.png'),
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Profile updated successfully')
            ->assertJsonPath('data.user.firstname', 'Updated')
            ->assertJsonPath('data.user.lastname', 'Reader');

        $photoUrl = $response->json('data.user.photo');

        $this->assertIsString($photoUrl);
        $this->assertStringStartsWith('/avatars/'.$user->id, $photoUrl);

        $freshUser = $user->fresh();
        $this->assertSame('Updated', $freshUser->firstname);
        $this->assertSame('Reader', $freshUser->lastname);
        $this->assertSame($photoUrl, $freshUser->avatar);
        $this->assertDatabaseHas('user_avatars', [
            'user_id' => $user->id,
            'mime_type' => 'image/png',
        ]);
    }

    public function test_avatar_binary_endpoint_returns_image_bytes(): void
    {
        $user = $this->createAuthenticatedUser();

        $this->post('/api/me/avatar', [
            'photo' => UploadedFile::fake()->image('avatar.png'),
        ])->assertOk();

        $response = $this->get('/avatars/'.$user->id);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/png');
        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('max-age=86400', $cacheControl);
    }

    private function createAuthenticatedUser(): User
    {
        DB::table('roles')->updateOrInsert(
            ['id' => 1],
            [
                'name' => 'reader',
                'description' => 'Reader role',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $user = User::factory()->create();

        Sanctum::actingAs($user);

        return $user;
    }
}
