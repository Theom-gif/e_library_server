<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminAuthorControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_author(): void
    {
        Storage::fake('public');
        $this->seedRoles();

        $admin = User::factory()->create(['role_id' => 1]);
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/admin/authors', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'bio' => 'Test bio',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'John Doe')
            ->assertJsonPath('data.email', 'john@example.com')
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('users', [
            'email' => 'john@example.com',
            'role_id' => 2,
        ]);
    }

    public function test_admin_can_list_and_filter_authors(): void
    {
        $this->seedRoles();

        $admin = User::factory()->create(['role_id' => 1]);
        User::factory()->create([
            'role_id' => 2,
            'firstname' => 'Active',
            'lastname' => 'Author',
            'email' => 'active@example.com',
            'is_active' => true,
        ]);
        User::factory()->create([
            'role_id' => 2,
            'firstname' => 'Pending',
            'lastname' => 'Author',
            'email' => 'pending@example.com',
            'is_active' => false,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/authors?status=pending&search=Pending');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.data.0.email', 'pending@example.com')
            ->assertJsonPath('data.total', 1);
    }

    public function test_admin_can_upload_profile_image_for_author(): void
    {
        Storage::fake('public');
        $this->seedRoles();

        $admin = User::factory()->create(['role_id' => 1]);
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/admin/authors', [
            'name' => 'Image Author',
            'email' => 'image@example.com',
            'profile_image' => UploadedFile::fake()->image('profile.jpg'),
        ]);

        $response->assertStatus(201);
        $this->assertNotNull($response->json('data.profile_image'));
    }

    public function test_admin_can_delete_author(): void
    {
        $this->seedRoles();

        $admin = User::factory()->create(['role_id' => 1]);
        $author = User::factory()->create(['role_id' => 2]);

        Sanctum::actingAs($admin);

        $response = $this->deleteJson('/api/admin/authors/'.$author->id);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('users', ['id' => $author->id]);
    }

    private function seedRoles(): void
    {
        foreach ([
            ['id' => 1, 'name' => 'Admin', 'description' => 'Administrator'],
            ['id' => 2, 'name' => 'Author', 'description' => 'Author'],
            ['id' => 3, 'name' => 'User', 'description' => 'User'],
        ] as $role) {
            DB::table('roles')->updateOrInsert(
                ['id' => $role['id']],
                [
                    'name' => $role['name'],
                    'description' => $role['description'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
