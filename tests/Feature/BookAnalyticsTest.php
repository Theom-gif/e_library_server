<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\ReadingProgress;
use App\Models\ReadingSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BookAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_author_books_list_includes_total_readers(): void
    {
        $author = User::factory()->create(['role_id' => 2]);
        $this->seedBookReaders($author);

        Sanctum::actingAs($author);

        $response = $this->getJson('/api/auth/books');

        $response->assertOk()
            ->assertJsonPath('data.0.totalReaders', 2)
            ->assertJsonPath('data.0.monthlyReads', 3);
    }

    public function test_author_can_fetch_per_book_analytics(): void
    {
        $author = User::factory()->create(['role_id' => 2]);
        $book = $this->seedBookReaders($author);

        Sanctum::actingAs($author);

        $response = $this->getJson('/api/books/'.$book->id.'/analytics');

        $response->assertOk()
            ->assertJsonPath('id', $book->id)
            ->assertJsonPath('totalReaders', 2)
            ->assertJsonPath('completionRate', 50.0)
            ->assertJsonPath('monthlyReads', 3);
    }

    private function seedBookReaders(User $author): Book
    {
        $book = Book::create([
            'user_id' => $author->id,
            'title' => 'Analytics Test Book',
            'slug' => 'analytics-test-book-'.uniqid(),
            'author_name' => trim($author->firstname.' '.$author->lastname),
            'description' => 'Book used for analytics tests.',
            'status' => 'approved',
            'approved_at' => now(),
            'published_at' => now(),
            'total_reads' => 0,
            'average_rating' => 0,
        ]);

        $readerOne = User::factory()->create(['role_id' => 3]);
        $readerTwo = User::factory()->create(['role_id' => 3]);

        ReadingSession::create([
            'book_id' => $book->id,
            'user_id' => $readerOne->id,
            'started_at' => now()->subDays(2),
            'ended_at' => now()->subDays(2)->addMinutes(10),
            'duration_seconds' => 600,
            'status' => 'finished',
            'last_activity_at' => now()->subDays(2),
            'last_heartbeat_at' => now()->subDays(2),
        ]);

        ReadingSession::create([
            'book_id' => $book->id,
            'user_id' => $readerOne->id,
            'started_at' => now()->subDay(),
            'ended_at' => now()->subDay()->addMinutes(5),
            'duration_seconds' => 300,
            'status' => 'finished',
            'last_activity_at' => now()->subDay(),
            'last_heartbeat_at' => now()->subDay(),
        ]);

        ReadingSession::create([
            'book_id' => $book->id,
            'user_id' => $readerTwo->id,
            'started_at' => now()->subHours(3),
            'ended_at' => now()->subHours(3)->addMinutes(7),
            'duration_seconds' => 420,
            'status' => 'active',
            'last_activity_at' => now()->subHours(3),
            'last_heartbeat_at' => now()->subHours(3),
        ]);

        ReadingProgress::create([
            'book_id' => $book->id,
            'user_id' => $readerOne->id,
            'last_page' => 120,
            'progress_percent' => 100,
            'total_seconds' => 900,
            'total_sessions' => 2,
            'total_days' => 2,
            'last_read_at' => now()->subDay(),
            'completed_at' => now()->subDay(),
        ]);

        ReadingProgress::create([
            'book_id' => $book->id,
            'user_id' => $readerTwo->id,
            'last_page' => 32,
            'progress_percent' => 35,
            'total_seconds' => 420,
            'total_sessions' => 1,
            'total_days' => 1,
            'last_read_at' => now()->subHours(3),
        ]);

        return $book;
    }
}
