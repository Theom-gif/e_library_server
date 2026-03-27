<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\admin\ApproveRejectBookRequest;
use App\Models\Book;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BookController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $status = $this->normalizeStatus((string) $request->query('status', 'All'));
        $search = trim((string) $request->query('search', ''));

        $query = Book::query()
            ->with(['category:id,name', 'author:id,firstname,lastname'])
            ->latest('id');

        if ($status !== null) {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $query->where(function (Builder $q) use ($search) {
                $q->where('title', 'like', '%'.$search.'%')
                    ->orWhere('author_name', 'like', '%'.$search.'%')
                    ->orWhere('author', 'like', '%'.$search.'%')
                    ->orWhere('category', 'like', '%'.$search.'%')
                    ->orWhereHas('category', function (Builder $categoryQuery) use ($search) {
                        $categoryQuery->where('name', 'like', '%'.$search.'%');
                    });
            });
        }

        $books = $query->get();

        return response()->json([
            'data' => $books->map(fn (Book $book) => $this->transformBook($book, true))->values(),
        ]);
    }

    public function approved(Request $request): JsonResponse
    {
        $request->merge(['status' => 'approved']);

        return $this->index($request);
    }

    public function approve(ApproveRejectBookRequest $request, Book $book): JsonResponse
    {
        $book->update(Book::compatibleAttributes([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'published_at' => now(),
            'rejection_reason' => null,
        ]));

        return response()->json($this->transformBook($book->fresh(['category', 'author']), true));
    }

    public function reject(ApproveRejectBookRequest $request, Book $book): JsonResponse
    {
        $book->update(Book::compatibleAttributes([
            'status' => 'rejected',
            'approved_by' => $request->user()->id,
            'approved_at' => null,
            'published_at' => null,
            'rejection_reason' => $request->input('rejection_reason'),
        ]));

        return response()->json($this->transformBook($book->fresh(['category', 'author']), true));
    }

    private function transformBook(Book $book, bool $includeDate): array
    {
        $authorName = $book->author_name
            ?: $book->author
            ?: trim((string) ($book->author?->firstname.' '.$book->author?->lastname));

        $categoryName = $book->category
            ?: $book->category?->name;

        $cover = $this->resolveCoverAsset($book->cover_image_path ?: $book->cover_image_url);
        $file = $this->resolveFileAsset($book->book_file_path ?: $book->pdf_path ?: $book->book_file_url);

        $dateSource = $book->published_at ?? $book->approved_at ?? $book->created_at;

        $payload = [
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
            'description' => $book->description,
            'first_publish_year' => $book->published_year ?? null,
            'manuscript_type' => $book->pdf_mime_type ?? null,
            'manuscript_size_bytes' => $book->file_size_bytes ?? null,
        ];

        if ($includeDate) {
            $payload['date'] = $dateSource?->format('M Y');
        }

        return $payload;
    }

    private function normalizeStatus(string $status): ?string
    {
        $value = strtolower(trim($status));
        if ($value === '' || $value === 'all') {
            return null;
        }

        return in_array($value, ['approved', 'pending', 'rejected'], true) ? $value : null;
    }

    private function formatStatus(?string $status): string
    {
        $value = strtolower((string) $status);
        if ($value === '') {
            $value = 'pending';
        }

        return ucfirst($value);
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
}
