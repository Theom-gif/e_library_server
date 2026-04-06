<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuthorControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_endpoint_returns_only_authors_with_profile_image_fields(): void
    {
        $author = User::factory()->create([
            'role_id' => 2,
            'firstname' => 'Author',
            'lastname' => 'Writer',
            'avatar' => 'avatars/author-12.jpg',
            'bio' => 'Writes books.',
        ]);

        User::factory()->create([
            'role_id' => 3,
            'firstname' => 'Regular',
            'lastname' => 'Reader',
        ]);

        $book = Book::create([
            'user_id' => $author->id,
            'title' => 'Author Book',
            'slug' => 'author-book',
            'author_name' => 'Author Writer',
            'description' => 'Testing author stats.',
            'status' => 'approved',
        ]);

        $reader = User::factory()->create(['role_id' => 3]);

        DB::table('favorite_authors')->insert([
            'user_id' => $reader->id,
            'author_id' => $author->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('book_ratings')->insert([
            'book_id' => $book->id,
            'user_id' => $reader->id,
            'rating' => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson('/api/users?role_id=2');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $author->id)
            ->assertJsonPath('data.0.role_id', 2)
            ->assertJsonPath('data.0.role_name', 'author')
            ->assertJsonPath('data.0.books_count', 1)
            ->assertJsonPath('data.0.followers_count', 1)
            ->assertJsonPath('data.0.avg_rating', 5.0);

        $payload = $response->json('data.0');

        $this->assertSame($payload['avatar_url'], $payload['avatar']);
        $this->assertSame($payload['avatar_url'], $payload['photo']);
        $this->assertSame($payload['avatar_url'], $payload['image_url']);
        $this->assertStringContainsString('/storage/', $payload['avatar_url']);
    }

    public function test_authors_endpoint_can_return_author_detail(): void
    {
        $author = User::factory()->create([
            'role_id' => 2,
            'firstname' => 'Detail',
            'lastname' => 'Author',
            'avatar' => 'https://example.com/avatar.jpg',
        ]);

        $response = $this->getJson('/api/authors/'.$author->id);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $author->id)
            ->assertJsonPath('data.role_id', 2)
            ->assertJsonPath('data.role_name', 'author')
            ->assertJsonPath('data.avatar_url', 'https://example.com/avatar.jpg');
    }

    public function test_users_endpoint_counts_books_owned_by_author_id(): void
    {
        $author = User::factory()->create([
            'role_id' => 2,
            'firstname' => 'Owned',
            'lastname' => 'Author',
        ]);

        $reader = User::factory()->create(['role_id' => 3]);

        $book = Book::create([
            'author_id' => $author->id,
            'title' => 'Owned By Author',
            'slug' => 'owned-by-author',
            'author_name' => 'Owned Author',
            'description' => 'Testing author ownership.',
            'status' => 'approved',
        ]);

        DB::table('book_ratings')->insert([
            'book_id' => $book->id,
            'user_id' => $reader->id,
            'rating' => 4,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson('/api/users?role_id=2');

        $response->assertOk()
            ->assertJsonPath('data.0.id', $author->id)
            ->assertJsonPath('data.0.books_count', 1)
            ->assertJsonPath('data.0.avg_rating', 4.0);
    }

    public function test_pending_authors_are_hidden_from_public_author_endpoints(): void
    {
        User::factory()->create([
            'role_id' => 2,
            'firstname' => 'Pending',
            'lastname' => 'Author',
            'email' => 'pending@author.test',
            'status' => 'in_review',
            'is_active' => false,
        ]);

        $activeAuthor = User::factory()->create([
            'role_id' => 2,
            'firstname' => 'Active',
            'lastname' => 'Author',
            'email' => 'active@author.test',
            'status' => 'active',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/authors');

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $activeAuthor->id);
    }
}
