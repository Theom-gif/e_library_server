<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookCover;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BookCoverResolutionTest extends TestCase
{
    public function test_it_returns_the_blob_cover_endpoint_when_a_database_cover_exists(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('books/covers/test-cover.jpg', 'fake-image-bytes');

        $book = new Book([
            'cover_image_path' => 'books/covers/test-cover.jpg',
            'cover_image_url' => '/storage/books/covers/test-cover.jpg',
        ]);
        $book->id = 42;
        $book->exists = true;
        $book->setRelation('coverImage', new BookCover([
            'book_id' => 42,
            'mime_type' => 'image/jpeg',
            'bytes' => 'blob-bytes',
            'hash' => 'hash-value',
        ]));

        $cover = $book->resolvedCoverAsset();

        $this->assertSame('books/covers/test-cover.jpg', $cover['path']);
        $this->assertSame('http://localhost:8000/api/books/42/cover', $cover['url']);
    }

    public function test_it_returns_an_absolute_cover_endpoint_for_blob_only_covers(): void
    {
        $book = new Book([
            'cover_image_path' => null,
            'cover_image_url' => null,
        ]);
        $book->id = 42;
        $book->exists = true;
        $book->setRelation('coverImage', new BookCover([
            'book_id' => 42,
            'mime_type' => 'image/jpeg',
            'bytes' => 'blob-bytes',
            'hash' => 'hash-value',
        ]));

        $cover = $book->resolvedCoverAsset();

        $this->assertNull($cover['path']);
        $this->assertSame('http://localhost:8000/api/books/42/cover', $cover['url']);
    }

    public function test_it_falls_back_to_the_blob_cover_endpoint_when_a_legacy_absolute_path_is_missing(): void
    {
        $book = new Book([
            'cover_image_path' => 'C:\\missing\\legacy-cover.jpg',
            'cover_image_url' => null,
        ]);
        $book->id = 42;
        $book->exists = true;
        $book->setRelation('coverImage', new BookCover([
            'book_id' => 42,
            'mime_type' => 'image/jpeg',
            'bytes' => 'blob-bytes',
            'hash' => 'hash-value',
        ]));

        $cover = $book->resolvedCoverAsset();

        $this->assertSame('C:\\missing\\legacy-cover.jpg', $cover['path']);
        $this->assertSame('http://localhost:8000/api/books/42/cover', $cover['url']);
    }
}
