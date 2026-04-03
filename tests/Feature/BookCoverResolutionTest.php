<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookCover;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BookCoverResolutionTest extends TestCase
{
    public function test_it_prefers_public_storage_cover_url_over_protected_cover_endpoint(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('books/covers/test-cover.jpg', 'fake-image-bytes');

        $book = new Book([
            'id' => 42,
            'cover_image_path' => 'books/covers/test-cover.jpg',
            'cover_image_url' => '/storage/books/covers/test-cover.jpg',
        ]);
        $book->exists = true;
        $book->setRelation('coverImage', new BookCover([
            'book_id' => 42,
            'mime_type' => 'image/jpeg',
            'bytes' => 'blob-bytes',
            'hash' => 'hash-value',
        ]));

        $cover = $book->resolvedCoverAsset();

        $this->assertSame('books/covers/test-cover.jpg', $cover['path']);
        $this->assertSame('/storage/books/covers/test-cover.jpg', $cover['url']);
    }
}
