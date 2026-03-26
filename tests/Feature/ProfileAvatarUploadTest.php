<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileAvatarUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_upload_avatar_via_dedicated_endpoint(): void
    {
        Storage::fake('public');

        $user = $this->createAuthenticatedUser();

        $response = $this->post('/api/me/avatar', [
            'avatar' => UploadedFile::fake()->image('avatar.jpg'),
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Avatar uploaded successfully');

        $avatarPath = $response->json('data.avatar');

        $this->assertIsString($avatarPath);
        $this->assertStringStartsWith('avatars/', $avatarPath);
        Storage::disk('public')->assertExists($avatarPath);

        $this->assertSame($avatarPath, $user->fresh()->avatar);
        $this->assertSame($avatarPath, $response->json('data.user.avatar'));
        $this->assertNotEmpty($response->json('data.avatar_url'));
        $this->assertSame($response->json('data.avatar_url'), $response->json('data.user.avatar_url'));
    }

    public function test_user_can_update_profile_with_avatar_via_post_multipart(): void
    {
        Storage::fake('public');

        $user = $this->createAuthenticatedUser();

        $response = $this->post('/api/me/profile', [
            'firstname' => 'Updated',
            'lastname' => 'Reader',
            'avatar' => UploadedFile::fake()->image('profile.png'),
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Profile updated successfully')
            ->assertJsonPath('data.user.firstname', 'Updated')
            ->assertJsonPath('data.user.lastname', 'Reader');

        $avatarPath = $response->json('data.user.avatar');

        $this->assertIsString($avatarPath);
        $this->assertStringStartsWith('avatars/', $avatarPath);
        Storage::disk('public')->assertExists($avatarPath);

        $freshUser = $user->fresh();
        $this->assertSame('Updated', $freshUser->firstname);
        $this->assertSame('Reader', $freshUser->lastname);
        $this->assertSame($avatarPath, $freshUser->avatar);
    }

    private function createAuthenticatedUser(): User
    {
        DB::table('roles')->insert([
            'id' => 1,
            'name' => 'reader',
            'description' => 'Reader role',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::factory()->create();

        Sanctum::actingAs($user);

        return $user;
    }
}
