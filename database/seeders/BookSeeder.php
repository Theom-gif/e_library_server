<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BookSeeder extends Seeder
{
    public function run(): void
    {
        $author = User::query()
            ->where('email', 'author@example.com')
            ->first();

        if (!$author) {
            return;
        }

        $categories = DB::table('categories')
            ->pluck('id', 'slug');

        $books = [
            [
                'title' => 'The Glass Kingdom',
                'slug' => 'the-glass-kingdom',
                'category_slug' => 'fantasy',
                'description' => 'A young archivist discovers a shattered realm hidden behind the palace mirrors.',
                'cover_image_url' => 'https://picsum.photos/seed/book-glass-kingdom/480/720',
            ],
            [
                'title' => 'Orbit of Memory',
                'slug' => 'orbit-of-memory',
                'category_slug' => 'science-fiction',
                'description' => 'A drifting station AI starts recovering memories that do not belong to any one human.',
                'cover_image_url' => 'https://picsum.photos/seed/book-orbit-memory/480/720',
            ],
            [
                'title' => 'Midnight in Cedar Lane',
                'slug' => 'midnight-in-cedar-lane',
                'category_slug' => 'mystery',
                'description' => 'A missing diary pulls a reluctant journalist into the oldest unsolved case in town.',
                'cover_image_url' => 'https://picsum.photos/seed/book-cedar-lane/480/720',
            ],
            [
                'title' => 'Letters Between Monsoons',
                'slug' => 'letters-between-monsoons',
                'category_slug' => 'romance',
                'description' => 'Two strangers keep finding each other through letters that arrive years too late.',
                'cover_image_url' => 'https://picsum.photos/seed/book-monsoons/480/720',
            ],
        ];

        foreach ($books as $book) {
            $payload = [
                'title' => $book['title'],
                'slug' => $book['slug'],
                'category_id' => $categories[$book['category_slug']] ?? null,
                'user_id' => $author->id,
                'author_id' => $author->id,
                'author_name' => trim($author->firstname.' '.$author->lastname),
                'description' => $book['description'],
                'cover_image_url' => $book['cover_image_url'],
                'cover_image_path' => null,
                'book_file_url' => 'https://example.com/demo-books/'.Str::slug($book['title']).'.pdf',
                'book_file_path' => null,
                'pdf_path' => 'https://example.com/demo-books/'.Str::slug($book['title']).'.pdf',
                'status' => 'approved',
                'approved_at' => now(),
                'published_at' => now()->subDays(rand(5, 45)),
                'average_rating' => rand(38, 49) / 10,
                'total_reads' => rand(25, 180),
                'language' => 'English',
            ];

            $existing = Book::query()->where('slug', $book['slug'])->first();

            if ($existing) {
                $existing->update(Book::compatibleAttributes($payload));
                continue;
            }

            Book::persistCompatible($payload);
        }
    }
}
