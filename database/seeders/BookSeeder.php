<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\Category;
use App\Models\User;
use App\Support\PublicImage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BookSeeder extends Seeder
{
     private function normalizeAvatar(?string $value): ?string
    {
        return PublicImage::normalize($value, 'avatars')['url'] ?? null;
    }

    private function normalizeCoverPath(?string $value): ?string
    {
        return PublicImage::normalize($value, 'avatars')['path'] ?? null;
    }

    public function run(): void
    {
        $author = User::query()
            ->where('email', 'author@example.com')
            ->first()
            ?? User::query()->where('role_id', 2)->first()
            ?? User::query()->first();

        if (!$author) {
            return;
        }

        $books = [
            [
                'title' => 'Midnight in Cedar Lane',
                'slug' => 'midnight-in-cedar-lane',
                'category_slug' => 'mystery',
                'description' => 'A missing diary pulls a reluctant journalist into the oldest unsolved case in town.',
                'cover_image_url' => 'books/covers/p6p7QLwRuHqfT2F3ewUbf4TCWA1vMANVAObxMKD6.jpg',
            ],
            [
                'title' => 'Letters Between Monsoons',
                'slug' => 'letters-between-monsoons',
                'category_slug' => 'romance',
                'description' => 'Two strangers keep finding each other through letters that arrive years too late.',
                'cover_image_url' => null,
            ],
        ];

        foreach ($books as $book) {
            $categoryId = $this->resolveCategoryId(
                $book['category_slug'],
                Str::headline($book['category_slug'])
            );
            $normalized = PublicImage::normalize($book['cover_image_url'] ?? null, 'books/covers') ?? [];
            $coverUrl = $normalized['url'] ?? null;
            $coverPath = $normalized['path'] ?? null;

            $payload = [
                'title' => $book['title'],
                'slug' => $book['slug'],
                'category_id' => $categoryId,
                'user_id' => $author->id,
                'author_id' => $author->id,
                'author_name' => trim($author->firstname.' '.$author->lastname),
                'description' => $book['description'],
                'cover_image_path' => $coverPath,
                'cover_image_url' => $coverUrl,
                'book_file_path' => 'https://example.com/demo-books/'.Str::slug($book['title']).'.pdf',
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

    private function resolveCategoryId(string $slug, string $name): ?int
    {
        $existing = Category::query()->where('slug', $slug)->first();
        if ($existing) {
            return (int) $existing->id;
        }

        $category = Category::create([
            'name' => $name,
            'slug' => $slug,
            'is_active' => true,
        ]);

        return (int) $category->id;
    }
}
