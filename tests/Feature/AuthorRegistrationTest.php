<?php

namespace Tests\Feature;

use App\Mail\AuthorStatusMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthorRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_author_registration_creates_admin_notification_even_if_email_fails(): void
    {
        $this->seedRoles();

        $admin = User::factory()->create([
            'role_id' => 1,
            'status' => 'active',
            'is_active' => true,
        ]);

        Mail::fake();
        Mail::shouldReceive('to->send')
            ->once()
            ->andThrow(new \RuntimeException('SMTP unavailable'));

        $response = $this->postJson('/api/auth/author_registration', [
            'firstname' => 'Tha',
            'lastname' => 'Lita',
            'email' => 'thalita@example.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'thalita@example.com');

        $authorId = (int) $response->json('data.user.id');

        $this->assertDatabaseHas('users', [
            'id' => $authorId,
            'role_id' => 2,
            'status' => 'in_review',
            'is_active' => false,
        ]);

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $admin->id,
            'created_by_user_id' => $authorId,
            'role' => 'admin',
            'type' => 'author.pending_approval',
        ]);
    }

    public function test_admin_notification_exposes_approve_and_reject_actions_for_author_requests(): void
    {
        $this->seedRoles();

        $admin = User::factory()->create([
            'role_id' => 1,
            'status' => 'active',
            'is_active' => true,
        ]);

        Mail::fake();

        $this->postJson('/api/auth/author_registration', [
            'firstname' => 'Tha',
            'lastname' => 'Lita',
            'email' => 'thalita-actions@example.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
        ])->assertCreated();

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/notifications');

        $response->assertOk()
            ->assertJsonPath('data.0.type', 'author.pending_approval')
            ->assertJsonPath('data.0.data.request_type', 'author_approval')
            ->assertJsonPath('data.0.data.can_approve', true)
            ->assertJsonPath('data.0.data.can_reject', true)
            ->assertJsonPath('data.0.data.actions.approve.method', 'POST')
            ->assertJsonPath('data.0.data.actions.reject.method', 'POST');

        $this->assertStringContainsString('/api/admin/approve-authors/', (string) $response->json('data.0.data.approve_endpoint'));
        $this->assertStringContainsString('/api/admin/reject-authors/', (string) $response->json('data.0.data.reject_endpoint'));
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
