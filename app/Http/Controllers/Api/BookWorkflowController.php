<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BookReviewRequest;
use App\Http\Requests\BookUploadRequest;
use App\Models\Book;
use App\Models\Category;
use App\Models\OfflineDownload;
use App\Models\User;
use App\Support\PublicImage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BookWorkflowController extends Controller
{
    public function userBooks(Request $request): JsonResponse
    {
        $perPage = max(1, min((int) $request->query('per_page', 15), 100));

        $books = Book::query()
            ->where('status', 'approved')
            ->latest('id')
            ->paginate($perPage);

        if ($books->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No approved books found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => array_map(fn (Book $book) => $this->transformSimpleBook($book), $books->items()),
            'meta' => [
                'current_page' => $books->currentPage(),
                'last_page' => $books->lastPage(),
                'per_page' => $books->perPage(),
                'total' => $books->total(),
            ],
        ]);
    }

    public function authorBooks(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'authorId' => 'required_without:author_id|integer|exists:users,id',
            'author_id' => 'required_without:authorId|integer|exists:users,id',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $authorId = (int) ($validated['authorId'] ?? $validated['author_id']);
        $perPage = max(1, min((int) ($validated['per_page'] ?? 15), 100));

        $books = Book::query()
            ->where(function (Builder $query) use ($authorId) {
                $query->where('author_id', $authorId)
                    ->orWhere('user_id', $authorId);
            })
            ->latest('id')
            ->paginate($perPage);

        if ($books->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No books found for this author.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => array_map(fn (Book $book) => $this->transformSimpleBook($book), $books->items()),
            'meta' => [
                'authorId' => $authorId,
                'current_page' => $books->currentPage(),
                'last_page' => $books->lastPage(),
                'per_page' => $books->perPage(),
                'total' => $books->total(),
            ],
        ]);
    }

    public function approvedBooks(Request $request): JsonResponse
    {
        $perPage = max(1, min((int) $request->query('per_page', 15), 100));
        $search = $this->resolveSearchKeyword($request);
        $categoryId = $this->resolveCategoryFilter($request);

        $query = Book::query()
            ->with(['category:id,name,slug', 'author:id,firstname,lastname'])
            ->where('status', 'approved');

        if ($search !== '') {
            $this->applyBookSearch($query, $search);
        }

        if ($categoryId !== null) {
            $query->where('category_id', $categoryId);
        }

        $this->applySorting($query, $request, 'published_at');
        $books = $query->paginate($perPage);

        $payload = array_map(fn (Book $book) => $this->transformBook($book), $books->items());

        return response()->json([
            'success' => true,
            'message' => 'Approved books retrieved successfully.',
            'data' => $payload,
            'books' => $payload,
            'results' => $payload,
            'meta' => [
                'current_page' => $books->currentPage(),
                'last_page' => $books->lastPage(),
                'per_page' => $books->perPage(),
                'total' => $books->total(),
            ],
        ]);
    }

    public function discoverBooks(Request $request): JsonResponse
    {
        $keyword = $this->resolveSearchKeyword($request);
        if ($keyword === '') {
            return response()->json([
                'success' => false,
                'message' => 'Search keyword is required.',
            ], 422);
        }

        $localLimit = max(1, min((int) $request->query('local_limit', 10), 30));
        $externalLimit = max(1, min((int) $request->query('external_limit', 10), 30));
        $includeExternal = !in_array(
            strtolower((string) $request->query('include_external', 'true')),
            ['0', 'false', 'no'],
            true
        );

        $localQuery = Book::query()
            ->with(['category:id,name,slug', 'author:id,firstname,lastname'])
            ->where('status', 'approved');

        $this->applyBookSearch($localQuery, $keyword);

        $localBooks = $localQuery
            ->orderByDesc('published_at')
            ->limit($localLimit)
            ->get();

        ['results' => $externalBooks, 'error' => $externalError] = $includeExternal
            ? $this->fetchOpenLibraryResults($keyword, $externalLimit)
            : ['results' => [], 'error' => null];

        return response()->json([
            'success' => true,
            'message' => 'Search results retrieved successfully.',
            'data' => [
                'keyword' => $keyword,
                'local' => array_map(
                    fn (Book $book) => array_merge(
                        ['source' => 'local'],
                        $this->transformBook($book)
                    ),
                    $localBooks->all()
                ),
                'external' => $externalBooks,
            ],
            'meta' => [
                'local_count' => $localBooks->count(),
                'external_count' => count($externalBooks),
                'external_error' => $externalError,
            ],
        ]);
    }

    public function authorResearch(Request $request): JsonResponse
    {
        $keyword = $this->resolveSearchKeyword($request);
        if ($keyword === '') {
            return response()->json([
                'success' => false,
                'message' => 'Search keyword is required.',
            ], 422);
        }

        $status = strtolower((string) $request->query('status', ''));
        $localLimit = max(1, min((int) $request->query('local_limit', 15), 50));
        $externalLimit = max(1, min((int) $request->query('external_limit', 10), 30));
        $includeExternal = !in_array(
            strtolower((string) $request->query('include_external', 'true')),
            ['0', 'false', 'no'],
            true
        );

        $query = Book::query()
            ->with(['category:id,name,slug'])
            ->where('author_id', $request->user()->id);

        if (in_array($status, ['pending', 'approved', 'rejected'], true)) {
            $query->where('status', $status);
        }

        $this->applyBookSearch($query, $keyword);
        $this->applySorting($query, $request, 'created_at');

        $localBooks = $query->limit($localLimit)->get();

        ['results' => $externalBooks, 'error' => $externalError] = $includeExternal
            ? $this->fetchOpenLibraryResults($keyword, $externalLimit)
            : ['results' => [], 'error' => null];

        return response()->json([
            'success' => true,
            'message' => 'Author research results retrieved successfully.',
            'data' => [
                'keyword' => $keyword,
                'local' => array_map(
                    fn (Book $book) => array_merge(
                        ['source' => 'local'],
                        $this->transformBook($book)
                    ),
                    $localBooks->all()
                ),
                'external' => $externalBooks,
            ],
            'meta' => [
                'local_count' => $localBooks->count(),
                'external_count' => count($externalBooks),
                'external_error' => $externalError,
            ],
        ]);
    }

    public function upload(BookUploadRequest $request): JsonResponse
    {
        $user = $request->user();
        $categoryId = $this->resolveCategoryId($request);
        $pdfFile = $request->file('pdf');
        $coverFile = $request->file('cover_image');

        $pdfPath = $pdfFile->store('books/pdfs', 'public');
        $storedCover = PublicImage::storeUploaded($coverFile, 'books/covers');
        $coverImagePath = $storedCover['path'] ?? null;

        try {
            $book = Book::create([
                'category_id' => $categoryId,
                'author_id' => $user->id,
                'title' => $request->title,
                'slug' => $this->createUniqueBookSlug($request->title),
                'description' => $request->input('description'),
                'author_name' => $request->input('author_name', trim($user->firstname.' '.$user->lastname)),
                'pdf_path' => $pdfPath,
                'original_pdf_name' => $pdfFile->getClientOriginalName(),
                'pdf_mime_type' => $pdfFile->getClientMimeType(),
                'cover_image_path' => $coverImagePath,
                'cover_image_url' => $storedCover['url'] ?? null,
                'original_cover_name' => $coverFile?->getClientOriginalName(),
                'cover_mime_type' => $coverFile?->getClientMimeType(),
                'file_size_bytes' => $pdfFile->getSize(),
                'total_pages' => $request->input('total_pages'),
                'language' => $request->input('language'),
                'status' => 'pending',
            ]);

            $this->notifyAdminsOfSubmission($book, $user);
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($pdfPath);
            if ($coverImagePath) {
                Storage::disk('public')->delete($coverImagePath);
            }

            return response()->json([
                'success' => false,
                'message' => 'Book upload failed.',
                'errors' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Book uploaded and submitted for admin approval.',
            'data' => $this->transformBook($book->fresh(['category', 'author'])),
        ], 201);
    }

    public function show(Request $request, Book $book): JsonResponse
    {
        if (!$this->canViewBook($request->user(), $book)) {
            return response()->json([
                'success' => false,
                'message' => 'Book not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Book retrieved successfully.',
            'data' => $this->transformBook($book->load(['category', 'author', 'approver'])),
        ]);
    }

    public function readPdf(Request $request, Book $book): BinaryFileResponse|JsonResponse
    {
        if (!$this->canViewBook($request->user(), $book)) {
            return response()->json([
                'success' => false,
                'message' => 'Book not found.',
            ], 404);
        }

        if (!$book->pdf_path || !Storage::disk('public')->exists($book->pdf_path)) {
            return response()->json([
                'success' => false,
                'message' => 'PDF file not found.',
            ], 404);
        }

        $path = Storage::disk('public')->path($book->pdf_path);
        $mimeType = $book->pdf_mime_type ?: (Storage::disk('public')->mimeType($book->pdf_path) ?: 'application/pdf');
        $filename = $book->original_pdf_name ?: basename($book->pdf_path);

        return response()->file($path, [
            'Content-Type' => $mimeType,
            'Content-Length' => (string) filesize($path),
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Access-Control-Expose-Headers' => 'Content-Length, Content-Disposition, Content-Type',
        ]);
    }

    public function resolveDownload(Request $request, Book $book): JsonResponse
    {
        if ($book->status !== 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Book not found.',
            ], 404);
        }

        $localPath = $this->resolveLocalBookPath($book);
        $absoluteUrl = $this->resolveAbsoluteBookUrl($book);

        if ($localPath !== null && Storage::disk('public')->exists($localPath)) {
            $resolvedMimeType = $book->pdf_mime_type ?: (Storage::disk('public')->mimeType($localPath) ?: 'application/pdf');
            $relativeReadUrl = route('api.books.read', ['book' => $book->id], false);
            $payload = [
                'download_url' => $relativeReadUrl,
                'stream_url' => $relativeReadUrl,
                'url' => $relativeReadUrl,
                'mime_type' => $resolvedMimeType,
                'file_name' => $book->original_pdf_name ?: basename($localPath),
                'size_bytes' => $book->file_size_bytes ?: Storage::disk('public')->size($localPath),
            ];

            $this->recordDownload($request, $book);

            return response()->json([
                'success' => true,
                'message' => 'Download link generated successfully.',
                'download_url' => $payload['download_url'],
                'stream_url' => $payload['stream_url'],
                'url' => $payload['url'],
                'data' => $payload,
            ]);
        }

        if ($absoluteUrl !== null) {
            $payload = [
                'download_url' => $absoluteUrl,
                'stream_url' => $absoluteUrl,
                'url' => $absoluteUrl,
                'mime_type' => $book->pdf_mime_type ?: 'application/pdf',
                'file_name' => $book->original_pdf_name ?: basename(parse_url($absoluteUrl, PHP_URL_PATH) ?: 'book.pdf'),
                'size_bytes' => $book->file_size_bytes,
            ];

            $this->recordDownload($request, $book);

            return response()->json([
                'success' => true,
                'message' => 'Download link generated successfully.',
                'download_url' => $payload['download_url'],
                'stream_url' => $payload['stream_url'],
                'url' => $payload['url'],
                'data' => $payload,
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Book file not found.',
        ], 404);
    }

    public function viewCover(Request $request, Book $book): BinaryFileResponse|JsonResponse
    {
        if (!$this->canViewBook($request->user(), $book)) {
            return response()->json([
                'success' => false,
                'message' => 'Book not found.',
            ], 404);
        }

        $cover = $this->resolveCoverAsset($book->cover_image_path ?: $book->cover_image_url);

        if ($cover['path'] && Storage::disk('public')->exists($cover['path'])) {
            return response()->file(
                Storage::disk('public')->path($cover['path']),
                [
                    'Content-Type' => Storage::disk('public')->mimeType($cover['path']) ?: 'application/octet-stream',
                    'Content-Disposition' => 'inline; filename="'.basename($cover['path']).'"',
                ]
            );
        }

        if ($cover['url'] && preg_match('/^https?:/i', $cover['url'])) {
            return response()->json([
                'success' => true,
                'data' => [
                    'cover_url' => $cover['url'],
                ],
            ]);
        }

        if ($cover['url'] && preg_match('/^data:/i', $cover['url'])) {
            return response()->json([
                'success' => false,
                'message' => 'Embedded cover images are not supported by this endpoint.',
            ], 404);
        }

        return response()->json([
            'success' => false,
            'message' => 'Cover image not found.',
        ], 404);
    }

    public function myBooks(Request $request): JsonResponse
    {
        $status = strtolower(trim((string) $request->query('status', 'approved')));
        $search = $this->resolveSearchKeyword($request);
        $categoryId = $this->resolveCategoryFilter($request);
        $perPage = max(1, min((int) $request->query('per_page', 15), 100));

        $query = Book::query()
            ->with(['category:id,name,slug'])
            ->where('author_id', $request->user()->id);

        if (in_array($status, ['all', 'any', '*'], true)) {
            // Return all statuses for the author when explicitly requested.
        } elseif (in_array($status, ['pending', 'approved', 'rejected'], true)) {
            $query->where('status', $status);
        } else {
            // Default "My Books" view shows only approved titles.
            $query->where('status', 'approved');
        }

        if ($search !== '') {
            $this->applyBookSearch($query, $search);
        }

        if ($categoryId !== null) {
            $query->where('category_id', $categoryId);
        }

        $this->applySorting($query, $request, 'created_at');
        $books = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Books retrieved successfully.',
            'data' => array_map(fn (Book $book) => $this->transformBook($book), $books->items()),
            'meta' => [
                'current_page' => $books->currentPage(),
                'last_page' => $books->lastPage(),
                'per_page' => $books->perPage(),
                'total' => $books->total(),
            ],
        ]);
    }

    public function pendingBooks(Request $request): JsonResponse
    {
        $perPage = max(1, min((int) $request->query('per_page', 15), 100));
        $search = $this->resolveSearchKeyword($request);
        $categoryId = $this->resolveCategoryFilter($request);

        $query = Book::query()
            ->with([
                'category:id,name,slug',
                'author:id,firstname,lastname,email',
            ])
            ->where('status', 'pending');

        if ($search !== '') {
            $this->applyBookSearch($query, $search);
        }

        if ($categoryId !== null) {
            $query->where('category_id', $categoryId);
        }

        $this->applySorting($query, $request, 'created_at');
        $books = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Pending books retrieved successfully.',
            'data' => array_map(fn (Book $book) => $this->transformBook($book), $books->items()),
            'meta' => [
                'current_page' => $books->currentPage(),
                'last_page' => $books->lastPage(),
                'per_page' => $books->perPage(),
                'total' => $books->total(),
            ],
        ]);
    }

    public function review(BookReviewRequest $request, Book $book): JsonResponse
    {
        if ($book->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Only pending books can be reviewed.',
            ], 422);
        }

        $status = $request->input('status');
        $isApproved = $status === 'approved';

        $book->update([
            'status' => $status,
            'approved_by' => $request->user()->id,
            'approved_at' => $isApproved ? now() : null,
            'published_at' => $isApproved ? now() : null,
            'rejection_reason' => $isApproved ? null : $request->input('rejection_reason'),
        ]);

        $this->notifyAuthorOfReview($book->fresh(['author']), $request->user());

        return response()->json([
            'success' => true,
            'message' => $isApproved ? 'Book approved successfully.' : 'Book rejected successfully.',
            'data' => $this->transformBook($book->fresh(['category', 'author', 'approver'])),
        ]);
    }

    private function resolveCategoryId(BookUploadRequest $request): int
    {
        $categoryId = $request->input('category_id');
        if ($categoryId !== null && $categoryId !== '') {
            return (int) $categoryId;
        }

        $categoryName = trim((string) $request->input('category'));
        if ($categoryName === '') {
            throw ValidationException::withMessages([
                'category' => ['Category is required.'],
            ]);
        }

        $existing = Category::query()
            ->whereRaw('LOWER(TRIM(name)) = ?', [strtolower($categoryName)])
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

    private function createUniqueBookSlug(string $title): string
    {
        return $this->createUniqueSlug(
            $title,
            fn (string $slug): bool => Book::query()->where('slug', $slug)->exists()
        );
    }

    private function createUniqueCategorySlug(string $name): string
    {
        return $this->createUniqueSlug(
            $name,
            fn (string $slug): bool => Category::query()->where('slug', $slug)->exists()
        );
    }

    private function createUniqueSlug(string $value, callable $exists): string
    {
        $base = Str::slug($value);
        if ($base === '') {
            $base = 'item';
        }

        $slug = $base;
        $counter = 2;
        while ($exists($slug)) {
            $slug = $base.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    private function notifyAdminsOfSubmission(Book $book, User $author): void
    {
        $adminIds = User::query()
            ->where('role_id', 1)
            ->pluck('id');

        if ($adminIds->isEmpty()) {
            return;
        }

        $now = now();
        $payload = json_encode([
            'book_id' => $book->id,
            'title' => $book->title,
            'author_id' => $author->id,
        ]);

        $rows = [];
        foreach ($adminIds as $adminId) {
            $rows[] = [
                'user_id' => $adminId,
                'created_by_user_id' => $author->id,
                'type' => 'book.pending_approval',
                'title' => 'New book submission pending approval',
                'message' => $author->firstname.' '.$author->lastname.' submitted "'.$book->title.'".',
                'payload' => $payload,
                'is_read' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('user_notifications')->insert($rows);
    }

    private function notifyAuthorOfReview(Book $book, User $admin): void
    {
        $isApproved = $book->status === 'approved';

        DB::table('user_notifications')->insert([
            'user_id' => $book->author_id,
            'created_by_user_id' => $admin->id,
            'type' => $isApproved ? 'book.approved' : 'book.rejected',
            'title' => $isApproved ? 'Book approved' : 'Book rejected',
            'message' => $isApproved
                ? 'Your book "'.$book->title.'" has been approved.'
                : 'Your book "'.$book->title.'" was rejected. Reason: '.($book->rejection_reason ?: 'No reason provided.'),
            'payload' => json_encode([
                'book_id' => $book->id,
                'status' => $book->status,
            ]),
            'is_read' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function transformBook(Book $book): array
    {
        $publicPdfUrl = $this->resolveStoredAssetUrl($book->pdf_path) ?: $book->book_file_url;
        $cover = $this->resolveCoverAsset($book->cover_image_path ?: $book->cover_image_url);

        return [
            'id' => $book->id,
            'title' => $book->title,
            'slug' => $book->slug,
            'description' => $book->description,
            'author_id' => $book->author_id,
            'author_name' => $book->author_name,
            'category_id' => $book->category_id,
            'category_name' => $book->category?->name,
            'status' => $book->status,
            'rejection_reason' => $book->rejection_reason,
            'approved_by' => $book->approved_by,
            'approved_at' => $book->approved_at,
            'published_at' => $book->published_at,
            'total_pages' => $book->total_pages,
            'language' => $book->language,
            'file_size_bytes' => $book->file_size_bytes,
            'pdf_path' => $book->pdf_path,
            'original_pdf_name' => $book->original_pdf_name,
            'pdf_mime_type' => $book->pdf_mime_type,
            'pdf_url' => $publicPdfUrl,
            'book_file_url' => $publicPdfUrl,
            'bookFileUrl' => $publicPdfUrl,
            'book_url' => $publicPdfUrl,
            'file_url' => $publicPdfUrl,
            'read_url' => route('api.books.read', ['book' => $book->id]),
            'cover_image_path' => $book->cover_image_path,
            'original_cover_name' => $book->original_cover_name,
            'cover_mime_type' => $book->cover_mime_type,
            'cover_image_url' => $cover['url'],
            'cover_view_url' => $cover['url'],
            'cover_api_url' => route('api.books.cover', ['book' => $book->id]),
            'cover_url' => $cover['url'],
            'cover' => $cover['url'],
            'poster' => $cover['url'],
            'created_at' => $book->created_at,
            'updated_at' => $book->updated_at,
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

    private function resolveStoredAssetUrl(?string $path): ?string
    {
        $value = trim((string) $path);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^(https?:|data:)/i', $value)) {
            return $value;
        }

        return Storage::disk('public')->url($value);
    }

    private function resolveSearchKeyword(Request $request): string
    {
        foreach (['search', 'q', 'query', 'keyword', 'research'] as $key) {
            $value = trim((string) $request->query($key, ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function resolveCategoryFilter(Request $request): ?int
    {
        $category = $request->query('category_id', $request->query('categoryId'));
        if ($category === null || $category === '') {
            return null;
        }

        return is_numeric($category) ? (int) $category : null;
    }

    private function applyBookSearch(Builder $query, string $search): void
    {
        $query->where(function (Builder $q) use ($search) {
            $q->where('title', 'like', '%'.$search.'%')
                ->orWhere('author_name', 'like', '%'.$search.'%')
                ->orWhere('description', 'like', '%'.$search.'%');
        });
    }

    private function applySorting(Builder $query, Request $request, string $defaultColumn): void
    {
        $sort = strtolower((string) $request->query('sort', $request->query('sort_by', 'newest')));
        $orderParam = strtolower((string) $request->query('order', ''));

        $column = match ($sort) {
            'title', 'title_asc', 'title_desc' => 'title',
            'rating', 'average_rating' => 'average_rating',
            'reads', 'total_reads' => 'total_reads',
            'updated', 'updated_at' => 'updated_at',
            'created', 'created_at' => 'created_at',
            'published', 'published_at' => 'published_at',
            default => $defaultColumn,
        };

        $direction = match ($sort) {
            'oldest', 'title_asc' => 'asc',
            'title' => 'asc',
            default => 'desc',
        };

        if (in_array($orderParam, ['asc', 'desc'], true)) {
            $direction = $orderParam;
        }

        $query->orderBy($column, $direction)->orderByDesc('id');
    }

    private function fetchOpenLibraryResults(string $keyword, int $limit): array
    {
        $externalBooks = [];
        $externalError = null;

        try {
            $response = Http::timeout(8)
                ->acceptJson()
                ->get('https://openlibrary.org/search.json', [
                    'q' => $keyword,
                    'limit' => $limit,
                ]);

            if ($response->successful()) {
                $docs = $response->json('docs', []);
                foreach (array_slice($docs, 0, $limit) as $doc) {
                    $title = (string) ($doc['title'] ?? '');
                    if ($title === '') {
                        continue;
                    }

                    $coverId = $doc['cover_i'] ?? null;
                    $openLibraryKey = $doc['key'] ?? null;
                    $externalBooks[] = [
                        'source' => 'open_library',
                        'title' => $title,
                        'author_name' => $doc['author_name'][0] ?? 'Unknown',
                        'published_year' => $doc['first_publish_year'] ?? null,
                        'language' => $doc['language'][0] ?? null,
                        'cover_url' => $coverId ? 'https://covers.openlibrary.org/b/id/'.$coverId.'-L.jpg' : null,
                        'open_library_key' => $openLibraryKey,
                        'open_library_url' => $openLibraryKey ? 'https://openlibrary.org'.$openLibraryKey : null,
                    ];
                }
            } else {
                $externalError = 'Open Library request failed with status '.$response->status().'.';
            }
        } catch (\Throwable $e) {
            $externalError = 'Open Library is temporarily unavailable.';
        }

        return [
            'results' => $externalBooks,
            'error' => $externalError,
        ];
    }

    private function canViewBook(?User $user, Book $book): bool
    {
        if ($book->status === 'approved') {
            return true;
        }

        if (!$user) {
            return false;
        }

        if ((int) $user->role_id === 1) {
            return true;
        }

        return (int) $user->id === (int) $book->author_id;
    }

    private function resolveLocalBookPath(Book $book): ?string
    {
        foreach ([$book->pdf_path, $book->book_file_path] as $candidate) {
            $value = trim((string) $candidate);
            if ($value !== '' && !preg_match('/^(https?:|data:)/i', $value)) {
                return $value;
            }
        }

        return null;
    }

    private function recordDownload(Request $request, Book $book): void
    {
        $user = $request->user();
        if (!$user) {
            return;
        }

        OfflineDownload::query()->updateOrCreate(
            [
                'book_id' => $book->id,
                'user_id' => $user->id,
            ],
            [
                'downloaded_at' => now(),
                'last_synced_at' => now(),
                'sync_status' => 'synced',
            ]
        );
    }

    private function resolveAbsoluteBookUrl(Book $book): ?string
    {
        foreach ([$book->book_file_url, $book->pdf_path, $book->book_file_path] as $candidate) {
            $value = trim((string) $candidate);
            if ($value !== '' && preg_match('/^https?:/i', $value)) {
                return $value;
            }
        }

        return null;
    }

    private function transformSimpleBook(Book $book): array
    {
        return [
            'id' => $book->id,
            'title' => $book->title,
            'authorId' => $book->author_id ?? $book->user_id,
            'status' => (string) $book->status,
            'created_at' => $book->created_at,
            'updated_at' => $book->updated_at,
        ];
    }
}
