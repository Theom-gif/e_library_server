<?php

namespace App\Http\Controllers\Api\Author;

use App\Http\Controllers\Controller;
use App\Http\Requests\author\StoreBookRequest;
use App\Http\Requests\author\UpdateBookRequest;
use App\Models\Book;
use App\Models\Category;
use App\Services\BookAnalyticsService;
use App\Support\PublicImage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BookController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $books = Book::query()
            ->with(['category:id,name'])
            ->where(function (Builder $query) use ($user) {
                $query->where('author_id', $user->id)
                    ->orWhere('user_id', $user->id);
            })
            ->latest('id')
            ->get();

        $analyticsByBookId = app(BookAnalyticsService::class)->forBooks($books->all());

        return response()->json([
            'data' => $books->map(function (Book $book) use ($analyticsByBookId) {
                return $this->transformBook($book, $analyticsByBookId[$book->id] ?? null);
            })->values(),
        ]);
    }

    public function show(Request $request, Book $book): JsonResponse
    {
        if (!$this->canAccess($request->user(), $book)) {
            return response()->json([
                'message' => 'Book not found.',
            ], 404);
        }

        return response()->json($this->transformBook(
            $book->load(['category']),
            app(BookAnalyticsService::class)->forBook($book)
        ));
    }

    public function analytics(Request $request, Book $book, BookAnalyticsService $analyticsService): JsonResponse
    {
        if (!$this->canAccess($request->user(), $book)) {
            return response()->json([
                'message' => 'Book not found.',
            ], 404);
        }

        return response()->json(
            $analyticsService->forBook($book)
        );
    }

    public function store(StoreBookRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $coverFile = $request->file('cover_image');
        $bookFile = $request->file('book_file');

        $book = DB::transaction(function () use ($validated, $user, $coverFile, $bookFile) {
            $categoryId = $this->resolveCategoryId($validated);
            $title = trim((string) $validated['title']);
            $authorName = $this->resolveAuthorName($validated, $user);
            $categoryName = $validated['category'] ?? Category::query()->find($categoryId)?->name;

            $storedBookPath = $bookFile ? $bookFile->store('books/pdfs', 'public') : null;
            $storedCover = PublicImage::storeUploaded($coverFile, 'books/covers');
            $normalizedCover = !$coverFile ? PublicImage::normalize($validated['cover_image_url'] ?? null, 'books/covers') : null;
            $storedCoverPath = $storedCover['path'] ?? $normalizedCover['path'] ?? null;

            $bookUrl = $validated['book_file_url'] ?? null;
            $coverUrl = $validated['cover_image_url'] ?? null;

            $payload = [
                'category_id' => $categoryId,
                'author_id' => $user->id,
                'title' => $title,
                'slug' => $this->createUniqueSlug($title),
                'description' => $validated['description'] ?? null,
                'author_name' => $authorName,
                'author' => $authorName,
                'category' => $categoryName,
                'published_year' => $validated['first_publish_year'] ?? null,
                'status' => 'pending',
                'approved_at' => null,
                'published_at' => null,
                'rejection_reason' => null,
                'user_id' => $user->id,
            ];

            if ($storedBookPath) {
                $payload['pdf_path'] = $storedBookPath;
                $payload['book_file_path'] = $storedBookPath;
                $payload['book_file_url'] = Storage::disk('public')->url($storedBookPath);
                $payload['pdf_mime_type'] = $bookFile?->getClientMimeType();
                $payload['file_size_bytes'] = $bookFile?->getSize();
            } elseif (is_string($bookUrl) && $bookUrl !== '') {
                $payload['pdf_path'] = $bookUrl;
                $payload['book_file_path'] = $bookUrl;
                $payload['book_file_url'] = $bookUrl;
            }

            if ($storedCoverPath) {
                $payload['cover_image_path'] = $storedCoverPath;
                $payload['cover_image_url'] = $storedCover['url'];
            } elseif (is_string($coverUrl) && $coverUrl !== '') {
                $payload['cover_image_path'] = $normalizedCover['path'] ?? null;
                $payload['cover_image_url'] = $normalizedCover['url'] ?? $coverUrl;
            }

            $book = Book::persistCompatible($payload);
            $book->syncCoverBlob($coverFile);

            return $book;
        });

        return response()->json($this->transformBook(
            $book->fresh(['category']),
            app(BookAnalyticsService::class)->forBook($book)
        ), 201);
    }

    public function update(UpdateBookRequest $request, Book $book): JsonResponse
    {
        if (!$this->canAccess($request->user(), $book)) {
            return response()->json([
                'message' => 'Book not found.',
            ], 404);
        }

        $validated = $request->validated();
        $coverFile = $request->file('cover_image');
        $bookFile = $request->file('book_file');

        $update = $validated;
        if (array_key_exists('first_publish_year', $update)) {
            $update['published_year'] = $update['first_publish_year'];
        }

        $categoryId = $this->resolveCategoryId($validated, false);
        if ($categoryId !== null) {
            $update['category_id'] = $categoryId;
            if (empty($update['category'])) {
                $update['category'] = Category::query()->find($categoryId)?->name;
            }
        }

        if (array_key_exists('title', $update)) {
            $update['slug'] = $this->createUniqueSlug($update['title'], $book->id);
        }

        if (array_key_exists('author', $update) && $update['author'] !== null) {
            $update['author_name'] = $update['author'];
        }

        if (array_key_exists('authorName', $update) && empty($update['author'])) {
            $update['author_name'] = $update['authorName'];
            $update['author'] = $update['authorName'];
        }

        if ($coverFile) {
            $this->deleteStoredAsset($book->cover_image_path);
            $storedCover = PublicImage::storeUploaded($coverFile, 'books/covers');
            $update['cover_image_path'] = $storedCover['path'];
            $update['cover_image_url'] = $storedCover['url'];
        } elseif (array_key_exists('cover_image_url', $update) && $update['cover_image_url']) {
            $normalizedCover = PublicImage::normalize((string) $update['cover_image_url'], 'books/covers');
            $update['cover_image_path'] = $normalizedCover['path'] ?? null;
            $update['cover_image_url'] = $normalizedCover['url'] ?? $update['cover_image_url'];
        }

        if ($bookFile) {
            $this->deleteStoredAsset($book->book_file_path ?: $book->pdf_path);
            $storedBookPath = $bookFile->store('books/pdfs', 'public');
            $update['pdf_path'] = $storedBookPath;
            $update['book_file_path'] = $storedBookPath;
            $update['book_file_url'] = Storage::disk('public')->url($storedBookPath);
            $update['pdf_mime_type'] = $bookFile->getClientMimeType();
            $update['file_size_bytes'] = $bookFile->getSize();
        } elseif (array_key_exists('book_file_url', $update) && $update['book_file_url']) {
            $update['pdf_path'] = $update['book_file_url'];
            $update['book_file_path'] = $update['book_file_url'];
        }

        unset($update['first_publish_year'], $update['authorName']);

        $book->update(Book::compatibleAttributes($update));
        if ($coverFile) {
            $book->syncCoverBlob($coverFile);
        }

        return response()->json($this->transformBook(
            $book->fresh(['category']),
            app(BookAnalyticsService::class)->forBook($book)
        ));
    }

    public function destroy(Request $request, Book $book): JsonResponse
    {
        if (!$this->canAccess($request->user(), $book)) {
            return response()->json([
                'message' => 'Book not found.',
            ], 404);
        }

        $this->deleteStoredAsset($book->cover_image_path);
        $this->deleteStoredAsset($book->book_file_path ?: $book->pdf_path);

        $book->delete();

        return response()->json(null, 204);
    }

    private function resolveCategoryId(array $validated, bool $required = true): ?int
    {
        $categoryId = $validated['category_id'] ?? null;
        if ($categoryId !== null && $categoryId !== '') {
            return (int) $categoryId;
        }

        $categoryName = trim((string) ($validated['category'] ?? ''));
        if ($categoryName === '') {
            return $required ? $this->defaultCategoryId() : null;
        }

        $existing = Category::query()
            ->whereRaw('LOWER(TRIM(name)) = ?', [Str::lower($categoryName)])
            ->first();

        if ($existing) {
            return (int) $existing->id;
        }

        $slug = $this->createUniqueCategorySlug($categoryName);

        $category = Category::create([
            'name' => $categoryName,
            'slug' => $slug,
            'is_active' => true,
        ]);

        return (int) $category->id;
    }

    private function defaultCategoryId(): ?int
    {
        $existing = Category::query()->orderBy('id')->first();
        if ($existing) {
            return (int) $existing->id;
        }

        $category = Category::create([
            'name' => 'General',
            'slug' => $this->createUniqueCategorySlug('General'),
            'is_active' => true,
        ]);

        return (int) $category->id;
    }

    private function createUniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'book';
        $slug = $base;
        $counter = 2;

        while (Book::query()
            ->when($ignoreId, fn (Builder $q) => $q->where('id', '!=', $ignoreId))
            ->where('slug', $slug)
            ->exists()) {
            $slug = $base.'-'.$counter;
            $counter++;
        }

        return $slug;
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

    private function resolveAuthorName(array $validated, $user): string
    {
        $author = $validated['author'] ?? $validated['authorName'] ?? null;
        if (is_string($author) && trim($author) !== '') {
            return trim($author);
        }

        $fullName = trim((string) ($user->firstname.' '.$user->lastname));
        return $fullName !== '' ? $fullName : 'Unknown';
    }

    private function canAccess($user, Book $book): bool
    {
        if (!$user) {
            return false;
        }

        if ((int) $user->role_id === 1) {
            return true;
        }

        return (int) $book->author_id === (int) $user->id
            || (int) $book->user_id === (int) $user->id;
    }

    private function transformBook(Book $book, ?array $analytics = null): array
    {
        $book->loadMissing('coverImage');
        $authorName = $book->author_name
            ?: $book->author
            ?: trim((string) ($book->author?->firstname.' '.$book->author?->lastname));

        $categoryName = $book->category
            ?: $book->category?->name;

        $cover = $book->resolvedCoverAsset();
        $file = $this->resolveFileAsset($book->book_file_path ?: $book->pdf_path ?: $book->book_file_url);

        return [
            'id' => $book->id,
            'title' => $book->title,
            'author' => $authorName ?: 'Unknown',
            'authorName' => $authorName ?: 'Unknown',
            'category' => $categoryName,
            'status' => $this->formatStatus($book->status),
            'downloads' => (int) ($book->total_reads ?? 0),
            'cover_image_url' => $cover['url'],
            'cover_image_path' => $cover['path'],
            'book_file_url' => $file['url'],
            'book_file_path' => $file['path'],
            'totalReaders' => (int) ($analytics['totalReaders'] ?? 0),
            'completionRate' => (float) ($analytics['completionRate'] ?? 0),
            'monthlyReads' => (int) ($analytics['monthlyReads'] ?? 0),
            'description' => $book->description,
            'first_publish_year' => $book->published_year ?? null,
            'manuscript_type' => $book->pdf_mime_type ?? null,
            'manuscript_size_bytes' => $book->file_size_bytes ?? null,
        ];
    }

    private function resolveCoverAsset(?string $pathOrUrl): array
    {
        $value = trim((string) $pathOrUrl);
        if ($value === '') {
            return ['path' => null, 'url' => null];
        }

        if ($this->isAbsoluteUrl($value)) {
            return ['path' => null, 'url' => $value];
        }

        if (Storage::disk('public')->exists($value)) {
            return [
                'path' => $value,
                'url' => Storage::disk('public')->url($value),
            ];
        }

        return ['path' => null, 'url' => null];
    }

    private function resolveFileAsset(?string $pathOrUrl): array
    {
        $value = trim((string) $pathOrUrl);
        if ($value === '') {
            return ['path' => null, 'url' => null];
        }

        if ($this->isAbsoluteUrl($value)) {
            return ['path' => null, 'url' => $value];
        }

        if (Storage::disk('public')->exists($value)) {
            return [
                'path' => $value,
                'url' => Storage::disk('public')->url($value),
            ];
        }

        return ['path' => null, 'url' => null];
    }

    private function isAbsoluteUrl(string $value): bool
    {
        return (bool) preg_match('/^(https?:|data:)/i', $value);
    }

    private function deleteStoredAsset(?string $path): void
    {
        $value = trim((string) $path);
        if ($value === '' || $this->isAbsoluteUrl($value)) {
            return;
        }

        Storage::disk('public')->delete($value);
    }

    private function formatStatus(?string $status): string
    {
        $value = strtolower((string) $status);
        if ($value === '') {
            $value = 'pending';
        }

        return ucfirst($value);
    }
}
