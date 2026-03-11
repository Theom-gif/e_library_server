<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BookInteractionController extends Controller
{
    public function listComments(Request $request, Book $book): JsonResponse
    {
        if ($book->status !== 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Comments are available only for approved books.',
            ], 404);
        }

        $perPage = max(1, min((int) $request->query('per_page', 20), 100));

        $comments = DB::table('book_comments as bc')
            ->leftJoin('users as u', 'u.id', '=', 'bc.user_id')
            ->where('bc.book_id', $book->id)
            ->orderByDesc('bc.created_at')
            ->select([
                'bc.id',
                'bc.book_id',
                'bc.user_id',
                'bc.parent_id',
                'bc.content',
                'bc.likes_count',
                'bc.is_edited',
                'bc.created_at',
                'bc.updated_at',
                'u.firstname',
                'u.lastname',
            ])
            ->paginate($perPage);

        $items = array_map(static function ($item): array {
            return [
                'id' => (int) $item->id,
                'book_id' => (int) $item->book_id,
                'user_id' => (int) $item->user_id,
                'parent_id' => $item->parent_id !== null ? (int) $item->parent_id : null,
                'content' => $item->content,
                'likes_count' => (int) $item->likes_count,
                'is_edited' => (bool) $item->is_edited,
                'created_at' => $item->created_at,
                'updated_at' => $item->updated_at,
                'user' => [
                    'id' => (int) $item->user_id,
                    'name' => trim(($item->firstname ?? '').' '.($item->lastname ?? '')),
                    'firstname' => $item->firstname,
                    'lastname' => $item->lastname,
                ],
            ];
        }, $comments->items());

        return response()->json([
            'success' => true,
            'message' => 'Comments retrieved successfully.',
            'data' => $items,
            'meta' => [
                'current_page' => $comments->currentPage(),
                'last_page' => $comments->lastPage(),
                'per_page' => $comments->perPage(),
                'total' => $comments->total(),
            ],
        ]);
    }

    public function addComment(Request $request, Book $book): JsonResponse
    {
        if ($book->status !== 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'You can comment only on approved books.',
            ], 422);
        }

        $validated = $request->validate([
            'content' => ['required', 'string', 'max:5000'],
            'parent_id' => ['nullable', 'integer', 'exists:book_comments,id'],
        ]);

        if (!empty($validated['parent_id'])) {
            $belongsToBook = DB::table('book_comments')
                ->where('id', (int) $validated['parent_id'])
                ->where('book_id', $book->id)
                ->exists();

            if (!$belongsToBook) {
                return response()->json([
                    'success' => false,
                    'message' => 'Parent comment is invalid for this book.',
                ], 422);
            }
        }

        $id = DB::table('book_comments')->insertGetId([
            'book_id' => $book->id,
            'user_id' => $request->user()->id,
            'parent_id' => $validated['parent_id'] ?? null,
            'content' => trim($validated['content']),
            'likes_count' => 0,
            'is_edited' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $comment = DB::table('book_comments as bc')
            ->leftJoin('users as u', 'u.id', '=', 'bc.user_id')
            ->where('bc.id', $id)
            ->select([
                'bc.id',
                'bc.book_id',
                'bc.user_id',
                'bc.parent_id',
                'bc.content',
                'bc.likes_count',
                'bc.is_edited',
                'bc.created_at',
                'bc.updated_at',
                'u.firstname',
                'u.lastname',
            ])
            ->first();

        return response()->json([
            'success' => true,
            'message' => 'Comment added successfully.',
            'data' => [
                'id' => (int) $comment->id,
                'book_id' => (int) $comment->book_id,
                'user_id' => (int) $comment->user_id,
                'parent_id' => $comment->parent_id !== null ? (int) $comment->parent_id : null,
                'content' => $comment->content,
                'likes_count' => (int) $comment->likes_count,
                'is_edited' => (bool) $comment->is_edited,
                'created_at' => $comment->created_at,
                'updated_at' => $comment->updated_at,
                'user' => [
                    'id' => (int) $comment->user_id,
                    'name' => trim(($comment->firstname ?? '').' '.($comment->lastname ?? '')),
                    'firstname' => $comment->firstname,
                    'lastname' => $comment->lastname,
                ],
            ],
        ], 201);
    }

    public function ratings(Request $request, Book $book): JsonResponse
    {
        if ($book->status !== 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Ratings are available only for approved books.',
            ], 404);
        }

        $aggregate = DB::table('book_ratings')
            ->where('book_id', $book->id)
            ->selectRaw('COUNT(*) as total_ratings, COALESCE(AVG(rating), 0) as average_rating')
            ->first();

        $distributionRows = DB::table('book_ratings')
            ->where('book_id', $book->id)
            ->selectRaw('rating, COUNT(*) as count')
            ->groupBy('rating')
            ->pluck('count', 'rating');

        $distribution = [];
        for ($rating = 1; $rating <= 5; $rating++) {
            $distribution[(string) $rating] = (int) ($distributionRows[$rating] ?? 0);
        }

        $userRating = null;
        if ($request->user()) {
            $userRating = DB::table('book_ratings')
                ->where('book_id', $book->id)
                ->where('user_id', $request->user()->id)
                ->value('rating');
        }

        return response()->json([
            'success' => true,
            'message' => 'Ratings retrieved successfully.',
            'data' => [
                'book_id' => $book->id,
                'average_rating' => round((float) ($aggregate->average_rating ?? 0), 2),
                'total_ratings' => (int) ($aggregate->total_ratings ?? 0),
                'distribution' => $distribution,
                'user_rating' => $userRating !== null ? (int) $userRating : null,
            ],
        ]);
    }

    public function rate(Request $request, Book $book): JsonResponse
    {
        if ($book->status !== 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'You can rate only approved books.',
            ], 422);
        }

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
        ]);

        $existing = DB::table('book_ratings')
            ->where('book_id', $book->id)
            ->where('user_id', $request->user()->id)
            ->exists();

        if ($existing) {
            DB::table('book_ratings')
                ->where('book_id', $book->id)
                ->where('user_id', $request->user()->id)
                ->update([
                    'rating' => (int) $validated['rating'],
                    'updated_at' => now(),
                ]);
        } else {
            DB::table('book_ratings')->insert([
                'book_id' => $book->id,
                'user_id' => $request->user()->id,
                'rating' => (int) $validated['rating'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $aggregate = DB::table('book_ratings')
            ->where('book_id', $book->id)
            ->selectRaw('COUNT(*) as total_ratings, COALESCE(AVG(rating), 0) as average_rating')
            ->first();

        $avg = round((float) ($aggregate->average_rating ?? 0), 2);
        $total = (int) ($aggregate->total_ratings ?? 0);

        DB::table('books')
            ->where('id', $book->id)
            ->update([
                'average_rating' => $avg,
                'updated_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Rating submitted successfully.',
            'data' => [
                'book_id' => $book->id,
                'rating' => (int) $validated['rating'],
                'average_rating' => $avg,
                'total_ratings' => $total,
            ],
        ]);
    }
}
