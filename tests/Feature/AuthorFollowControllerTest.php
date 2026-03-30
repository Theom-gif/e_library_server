<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthorFollowControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_follow_and_unfollow_an_author(): void
    {
        $reader = User::factory()->create(['role_id' => 3]);
        $author = User::factory()->create([
            'role_id' => 2,
            'firstname' => 'Emily',
            'lastname' => 'Author',
        ]);

        Sanctum::actingAs($reader);

        $followResponse = $this->postJson('/api/authors/'.$author->id.'/follow');

        $followResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.author_id', $author->id)
            ->assertJsonPath('data.is_following', true)
            ->assertJsonPath('data.followers_count', 1);

        $this->assertDatabaseHas('favorite_authors', [
            'user_id' => $reader->id,
            'author_id' => $author->id,
        ]);

        $repeatFollowResponse = $this->postJson('/api/authors/'.$author->id.'/follow');

        $repeatFollowResponse->assertOk()
            ->assertJsonPath('data.followers_count', 1);

        $unfollowResponse = $this->deleteJson('/api/authors/'.$author->id.'/follow');

        $unfollowResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.author_id', $author->id)
            ->assertJsonPath('data.is_following', false)
            ->assertJsonPath('data.followers_count', 0);

        $this->assertDatabaseMissing('favorite_authors', [
            'user_id' => $reader->id,
            'author_id' => $author->id,
        ]);
    }

    public function test_following_list_returns_author_cards_for_current_user(): void
    {
        $reader = User::factory()->create(['role_id' => 3]);
        $followedAuthor = User::factory()->create([
            'role_id' => 2,
            'firstname' => 'Followed',
            'lastname' => 'Author',
            'avatar' => 'avatars/followed.jpg',
        ]);
        $otherAuthor = User::factory()->create([
            'role_id' => 2,
            'firstname' => 'Other',
            'lastname' => 'Author',
        ]);

        DB::table('favorite_authors')->insert([
            [
                'user_id' => $reader->id,
                'author_id' => $followedAuthor->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => User::factory()->create(['role_id' => 3])->id,
                'author_id' => $followedAuthor->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => User::factory()->create(['role_id' => 3])->id,
                'author_id' => $otherAuthor->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        Sanctum::actingAs($reader);

        $response = $this->getJson('/api/me/following/authors');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $followedAuthor->id)
            ->assertJsonPath('data.0.name', 'Followed Author')
            ->assertJsonPath('data.0.is_following', true)
            ->assertJsonPath('data.0.followers_count', 2)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_authenticated_author_detail_includes_follow_state(): void
    {
        $reader = User::factory()->create(['role_id' => 3]);
        $author = User::factory()->create([
            'role_id' => 2,
            'firstname' => 'Detail',
            'lastname' => 'Followed',
        ]);

        DB::table('favorite_authors')->insert([
            'user_id' => $reader->id,
            'author_id' => $author->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($reader);

        $response = $this->getJson('/api/authors/'.$author->id);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $author->id)
            ->assertJsonPath('data.is_following', true)
            ->assertJsonPath('data.followers_count', 1);
    }
}
