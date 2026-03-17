<?php

namespace App\Http\Controllers\Api\Reader;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\Favorite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Favorite::query()
            ->where('user_id', $user?->id)
            ->with(['book.category']);

        if ($this->isReader($user?->role_id)) {
            $query->whereHas('book', function ($bookQuery) {
                $bookQuery->where('status', 'approved');
            });
        }

        $favorites = $query->get();

        $books = [];
        foreach ($favorites as $favorite) {
            $book = $favorite->book;
            if (!$book) {
                continue;
            }

            $books[] = $this->transformBook($book);
        }

        return response()->json([
            'success' => true,
            'data' => $books,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'book_id' => 'required|integer|exists:books,id',
        ]);

        $user = $request->user();
        $book = Book::query()->find($data['book_id']);

        if (!$book) {
            return response()->json([
                'success' => false,
                'message' => 'Book not found.',
            ], 404);
        }

        if ($this->isReader($user?->role_id) && $book->status !== 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Book not found.',
            ], 404);
        }

        $existing = Favorite::query()
            ->where('user_id', $user?->id)
            ->where('book_id', $data['book_id'])
            ->first();

        if ($existing) {
            return response()->json([
                'success' => true,
                'message' => 'Already in favorites.',
                'data' => ['book_id' => (int) $data['book_id']],
            ], 200);
        }

        Favorite::create([
            'user_id' => $user?->id,
            'book_id' => $data['book_id'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Added to favorites',
            'data' => ['book_id' => (int) $data['book_id']],
        ], 201);
    }

    public function destroy(Request $request, int $bookId): JsonResponse
    {
        $user = $request->user();

        Favorite::query()
            ->where('user_id', $user?->id)
            ->where('book_id', $bookId)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Removed from favorites',
        ]);
    }

    public function toggle(Request $request): JsonResponse
    {
        $data = $request->validate([
            'book_id' => 'required|integer|exists:books,id',
        ]);

        $user = $request->user();
        $book = Book::query()->find($data['book_id']);

        if (!$book) {
            return response()->json([
                'success' => false,
                'message' => 'Book not found.',
            ], 404);
        }

        if ($this->isReader($user?->role_id) && $book->status !== 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Book not found.',
            ], 404);
        }

        $existing = Favorite::query()
            ->where('user_id', $user?->id)
            ->where('book_id', $data['book_id'])
            ->first();

        if ($existing) {
            $existing->delete();

            return response()->json([
                'success' => true,
                'message' => 'Removed from favorites.',
                'data' => ['is_favorite' => false],
            ]);
        }

        Favorite::create([
            'user_id' => $user?->id,
            'book_id' => $data['book_id'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Added to favorites.',
            'data' => ['is_favorite' => true],
        ], 201);
    }

    private function isReader(?int $roleId): bool
    {
        return (int) $roleId === 3;
    }

    /**
     * @return array<string, mixed>
     */
    private function transformBook(Book $book): array
    {
        $api = $book->toApiArray();

        return [
            'id' => $book->id,
            'title' => $book->title,
            'author_name' => $book->author_name ?? ($api['author'] ?? null),
            'category_name' => $book->category?->name ?? ($book->category ?? null),
            'cover_image_url' => $api['cover_image_url'] ?? null,
            'average_rating' => $book->average_rating,
            'status' => $book->status,
        ];
    }
}
