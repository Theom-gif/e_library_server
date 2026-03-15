<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\admin\category\BookUploadRequest;
use App\Models\Book;
use App\Models\Category;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BookController extends Controller
{
    /**
     * Upload and store a new book.
     */
    public function store(BookUploadRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $coverFile = $request->file('cover_image') ?? $request->file('coverImage');
            $bookFile = $request->file('book_file') ?? $request->file('bookFile');
            $bookFileUrl = $validated['book_file_url'] ?? $validated['bookFileUrl'] ?? null;
            $coverImageUrl = $validated['cover_image_url'] ?? $validated['coverImageUrl'] ?? null;

            if (!$bookFile && !$bookFileUrl) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => [
                        'book_file' => ['Book file upload or book_file_url is required.'],
                    ],
                ], 422);
            }

            $book = DB::transaction(function () use ($request, $validated, $coverFile, $bookFile, $bookFileUrl, $coverImageUrl) {
                $author = $this->resolveAuthor($request->user(), $validated['user_id'] ?? null);
                $category = $this->resolveCategory($validated['category'] ?? null);

                $storedBookPath = $bookFile ? $bookFile->store('books/pdfs', 'public') : null;
                $storedCoverPath = $coverFile ? $coverFile->store('books/covers', 'public') : null;

                $title = trim((string) ($validated['title'] ?? 'Untitled Book'));
                $slug = $this->createUniqueSlug($title);
                $requestedStatus = $request->input('status');
                $status = in_array($requestedStatus, ['pending', 'approved', 'rejected'], true)
                    ? $requestedStatus
                    : 'approved';

                $payload = [
                    // New workflow fields
                    'category_id' => $category->id,
                    'author_id' => $author->id,
                    'title' => $title,
                    'slug' => $slug,
                    'description' => $validated['description'] ?? null,
                    'author_name' => $validated['author'] ?? trim(($author->firstname ?? '').' '.($author->lastname ?? '')) ?: 'Unknown',
                    'pdf_path' => $storedBookPath ?? (string) $bookFileUrl,
                    'original_pdf_name' => $bookFile?->getClientOriginalName(),
                    'pdf_mime_type' => $bookFile?->getClientMimeType(),
                    'cover_image_path' => $storedCoverPath,
                    'original_cover_name' => $coverFile?->getClientOriginalName(),
                    'cover_mime_type' => $coverFile?->getClientMimeType(),
                    'file_size_bytes' => $bookFile?->getSize(),
                    'total_pages' => null,
                    'language' => null,
                    'status' => $status,
                    'approved_at' => $status === 'approved' ? now() : null,
                    'published_at' => $status === 'approved' ? now() : null,

                    // Legacy compatibility fields
                    'author' => $validated['author'] ?? null,
                    'category' => $validated['category'] ?? $category->name,
                    'published_year' => $validated['published_year'] ?? null,
                    'user_id' => $author->id,
                    'book_file_path' => $storedBookPath,
                    'book_file_url' => $bookFile
                        ? url(Storage::disk('public')->url($storedBookPath))
                        : $bookFileUrl,
                    'cover_image_url' => $coverFile
                        ? url(Storage::disk('public')->url($storedCoverPath))
                        : $coverImageUrl,
                ];

                return Book::persistCompatible($payload);
            });

            $bookArray = $book->toApiArray();

            return response()->json([
                'success' => true,
                'message' => 'Book uploaded successfully',
                'data' => $bookArray,
                'book' => $bookArray,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload book',
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    private function resolveAuthor(?User $authUser, mixed $userId): User
    {
        if ($authUser) {
            return $authUser;
        }

        if ($userId && is_numeric($userId)) {
            $existing = User::query()->find((int) $userId);
            if ($existing) {
                return $existing;
            }
        }

        $fallback = User::query()
            ->orderByRaw("CASE WHEN role_id = 2 THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->first();

        if (!$fallback) {
            throw new \RuntimeException('No user found. Please create at least one user before uploading books.');
        }

        return $fallback;
    }

    private function resolveCategory(?string $categoryName): Category
    {
        $name = trim((string) $categoryName);
        if ($name === '') {
            $name = 'General';
        }

        $existing = Category::query()
            ->whereRaw('LOWER(TRIM(name)) = ?', [Str::lower($name)])
            ->first();

        if ($existing) {
            return $existing;
        }

        $slug = $this->createUniqueCategorySlug($name);

        return Category::create([
            'name' => $name,
            'slug' => $slug,
            'is_active' => true,
        ]);
    }

    private function createUniqueCategorySlug(string $name): string
    {
        $base = Str::slug($name) ?: 'category';
        $slug = $base;
        $counter = 2;

        while (Category::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    private function createUniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'book';
        $slug = $base;
        $counter = 2;

        while (Book::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$counter;
            $counter++;
        }

        return $slug;
    }
}
