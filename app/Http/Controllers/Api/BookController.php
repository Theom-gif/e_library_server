<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\admin\category\BookViewRequest;
use App\Http\Requests\admin\category\BookUploadRequest;
use App\Models\Book;
use App\Models\Category;
use App\Models\User;
use App\Support\PublicImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BookController extends Controller
{
    public function listBooks(Request $request): JsonResponse
    {
        try {
            $includeDeleted = in_array(
                strtolower((string) $request->query('include_deleted', '0')),
                ['1', 'true', 'yes'],
                true
            );

            $query = Book::query()->latest('id');
            if ($includeDeleted) {
                $query->withTrashed();
            }

            $books = $query->get()->map(function (Book $book) {
                $item = $book->toApiArray();
                $item['is_deleted'] = $book->trashed();

                return $item;
            })->values();

            return response()->json([
                'success' => true,
                'message' => 'Books retrieved successfully',
                'data' => $books,
                'books' => $books,
                'results' => $books,
            ], 200);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve books', $e->getMessage(), 500);
        }
    }

    public function importBooks(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'books' => 'required|array|min:1',
                'books.*.title' => 'required|string|max:255',
                'books.*.author' => 'nullable|string|max:255',
                'books.*.description' => 'nullable|string',
                'books.*.category' => 'nullable|string|max:255',
                'books.*.published_year' => 'nullable|integer|min:1000|max:' . (now()->year + 1),
                'books.*.cover_image_url' => 'nullable|url|max:2048',
                'books.*.book_file_url' => 'nullable|url|max:2048',
            ]);

            $created = 0;
            foreach ($validated['books'] as $item) {
                $book = new Book();
                $book->title = $item['title'];
                $book->author = $item['author'] ?? null;
                $book->description = $item['description'] ?? null;
                $book->category = $item['category'] ?? null;
                $book->published_year = $item['published_year'] ?? null;
                $book->cover_image_url = $item['cover_image_url'] ?? null;
                $book->book_file_url = $item['book_file_url'] ?? null;
                $book->user_id = optional($request->user())->id;
                $book->save();
                $created++;
            }

            return $this->successResponse([
                'imported_count' => $created,
            ], 'Books imported successfully', 201);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to import books', $e->getMessage(), 500);
        }
    }

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
                $storedCover = PublicImage::storeUploaded($coverFile, 'books/covers');
                $normalizedCover = !$coverFile ? PublicImage::normalize($coverImageUrl, 'books/covers') : null;
                $storedCoverPath = $storedCover['path'] ?? $normalizedCover['path'] ?? null;

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
                        ? Storage::disk('public')->url($storedBookPath)
                        : $bookFileUrl,
                    'cover_image_url' => $storedCover['url'] ?? $normalizedCover['url'] ?? $coverImageUrl,
                ];

                $book = Book::persistCompatible($payload);
                $book->syncCoverBlob($coverFile);

                return $book;
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

    public function updateBook(Request $request, int $id): JsonResponse
    {
        try {
            $book = Book::find($id);
            if (!$book) {
                return $this->errorResponse('Book not found', null, 404);
            }

            $validated = $request->validate([
                'category_id' => 'sometimes|nullable|integer|exists:categories,id',
                'author_id' => 'sometimes|nullable|integer|exists:users,id',
                'approved_by' => 'sometimes|nullable|integer|exists:users,id',
                'title' => 'sometimes|required|string|max:255',
                'slug' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('books', 'slug')->ignore($book->id)],
                'author' => 'sometimes|nullable|string|max:255',
                'author_name' => 'sometimes|nullable|string|max:255',
                'description' => 'sometimes|nullable|string',
                'category' => 'sometimes|nullable|string|max:255',
                'published_year' => 'sometimes|nullable|integer|min:1000|max:' . (now()->year + 1),
                'pdf_path' => 'sometimes|nullable|string|max:2048',
                'book_file_path' => 'sometimes|nullable|string|max:2048',
                'cover_image_path' => 'sometimes|nullable|string|max:2048',
                'cover_image' => 'sometimes|nullable|image|max:5120',
                'coverImage' => 'sometimes|nullable|image|max:5120',
                'book_file' => 'sometimes|nullable|file|mimes:pdf,epub,doc,docx|max:20480',
                'bookFile' => 'sometimes|nullable|file|mimes:pdf,epub,doc,docx|max:20480',
                'cover_image_url' => 'sometimes|nullable|string|max:2048',
                'coverImageUrl' => 'sometimes|nullable|string|max:2048',
                'book_file_url' => 'sometimes|nullable|string|max:2048',
                'bookFileUrl' => 'sometimes|nullable|string|max:2048',
                'status' => ['sometimes', 'nullable', Rule::in(['pending', 'approved', 'rejected'])],
                'approved_at' => 'sometimes|nullable|date',
                'rejection_reason' => 'sometimes|nullable|string',
                'published_at' => 'sometimes|nullable|date',
                'language' => 'sometimes|nullable|string|max:12',
                'total_pages' => 'sometimes|nullable|integer|min:1',
                'file_size_bytes' => 'sometimes|nullable|integer|min:0',
            ]);

            $coverFile = $request->file('cover_image') ?? $request->file('coverImage');
            $bookFile = $request->file('book_file') ?? $request->file('bookFile');
            $bookFileUrl = $validated['book_file_url'] ?? $validated['bookFileUrl'] ?? null;
            $coverImageUrl = $validated['cover_image_url'] ?? $validated['coverImageUrl'] ?? null;

            if ($coverFile) {
                if ($book->cover_image_path) {
                    Storage::disk('public')->delete($book->cover_image_path);
                }
                $storedCover = PublicImage::storeUploaded($coverFile, 'books/covers');
                $validated['cover_image_path'] = $storedCover['path'];
                $validated['cover_image_url'] = $storedCover['url'];
                $validated['original_cover_name'] = $coverFile->getClientOriginalName();
                $validated['cover_mime_type'] = $coverFile->getClientMimeType();
            }

            if ($bookFile) {
                $oldPdfPath = $book->pdf_path ?: $book->book_file_path;
                if ($oldPdfPath && !preg_match('/^(https?:|data:)/i', (string) $oldPdfPath)) {
                    Storage::disk('public')->delete($oldPdfPath);
                }
                $validated['pdf_path'] = $bookFile->store('books/pdfs', 'public');
                $validated['book_file_path'] = $validated['pdf_path'];
                $validated['original_pdf_name'] = $bookFile->getClientOriginalName();
                $validated['pdf_mime_type'] = $bookFile->getClientMimeType();
                $validated['file_size_bytes'] = $bookFile->getSize();
            }

            if (!empty($validated['pdf_path']) && empty($validated['book_file_url'])) {
                $validated['book_file_url'] = Storage::disk('public')->url($validated['pdf_path']);
            }

            if (!$coverFile && is_string($coverImageUrl) && $coverImageUrl !== '') {
                $normalizedCover = PublicImage::normalize($coverImageUrl, 'books/covers');
                $validated['cover_image_url'] = $normalizedCover['url'] ?? $coverImageUrl;
                $validated['cover_image_path'] = $normalizedCover['path'] ?? null;
                $validated['original_cover_name'] = null;
                $validated['cover_mime_type'] = null;
            }

            if (!$bookFile && is_string($bookFileUrl) && $bookFileUrl !== '') {
                $validated['book_file_url'] = $bookFileUrl;
                $validated['pdf_path'] = $bookFileUrl;
                $validated['book_file_path'] = $bookFileUrl;
            }

            if (array_key_exists('author', $validated)) {
                $validated['author_name'] = $validated['author'];
            }
            if (array_key_exists('author_name', $validated) && !array_key_exists('author', $validated)) {
                $validated['author'] = $validated['author_name'];
            }

            if (array_key_exists('category', $validated)) {
                $categoryName = trim((string) $validated['category']);
                if ($categoryName !== '') {
                    $existingCategory = Category::query()
                        ->whereRaw('LOWER(TRIM(name)) = ?', [Str::lower($categoryName)])
                        ->first();

                    if (!$existingCategory) {
                        $baseSlug = Str::slug($categoryName) ?: 'category';
                        $slug = $baseSlug;
                        $counter = 2;
                        while (Category::query()->where('slug', $slug)->exists()) {
                            $slug = $baseSlug.'-'.$counter;
                            $counter++;
                        }

                        $existingCategory = Category::create([
                            'name' => $categoryName,
                            'slug' => $slug,
                            'is_active' => true,
                        ]);
                    }

                    $validated['category_id'] = $existingCategory->id;
                    $validated['category'] = $existingCategory->name;
                }
            }
            if (array_key_exists('category_id', $validated) && $validated['category_id']) {
                $existingCategory = Category::query()->find($validated['category_id']);
                if ($existingCategory) {
                    $validated['category'] = $existingCategory->name;
                }
            }

            unset(
                $validated['cover_image'],
                $validated['book_file'],
                $validated['coverImage'],
                $validated['bookFile'],
                $validated['coverImageUrl'],
                $validated['bookFileUrl']
            );

            $book->update(Book::compatibleAttributes($validated));
            if ($coverFile) {
                $book->syncCoverBlob($coverFile);
            }
            $book->refresh();

            return $this->successResponse($book->toApiArray(), 'Book updated successfully', 200);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update book', $e->getMessage(), 500);
        }
    }

    public function deleteBook(int $id): JsonResponse
    {
        try {
            $book = Book::withTrashed()->find($id);
            if (!$book) {
                return $this->errorResponse('Book not found', null, 404);
            }

            if ($book->cover_image_path) {
                Storage::disk('public')->delete($book->cover_image_path);
            }

            $bookAssetPaths = array_filter([
                $book->pdf_path,
                $book->book_file_path,
            ]);
            foreach (array_unique($bookAssetPaths) as $path) {
                if (!preg_match('/^(https?:|data:)/i', (string) $path)) {
                    Storage::disk('public')->delete($path);
                }
            }

            $book->forceDelete();

            return $this->successResponse(null, 'Book deleted successfully', 200);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to delete book', $e->getMessage(), 500);
        }
    }

    public function storeBookView(BookViewRequest $request): JsonResponse
    {
        try {
            $now = now();
            $bookViewId = DB::table('book_views')->insertGetId([
                'book_id' => $request->book_id,
                'user_id' => $request->user_id ?? optional($request->user())->id,
                'ip_address' => $request->ip_address ?? $request->ip(),
                'user_agent' => $request->user_agent ?? $request->userAgent(),
                'viewed_at' => $request->viewed_at ?? $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $bookView = DB::table('book_views')->where('id', $bookViewId)->first();

            return $this->successResponse($bookView, 'Book view saved successfully', 201);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to save book view', $e->getMessage(), 500);
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

    private function successResponse($data, string $message, int $code = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    private function errorResponse(string $message, $errors = null, int $code = 400): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $code);
    }
}
