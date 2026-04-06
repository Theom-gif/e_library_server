<?php

namespace App\Http\Controllers\Api\Reader;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\User;
use App\Support\PublicImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ReviewController extends Controller
{
    public function listComments(Request $request, Book $book): JsonResponse
    {
        if (!$this->isBookReviewable($book)) {
            return response()->json([
                'success' => false,
                'message' => 'Comments are available only for approved books.',
            ], 404);
        }

        $perPage = max(1, min((int) $request->query('per_page', 20), 100));

        $comments = DB::table('book_comments as bc')
            ->leftJoin('users as u', 'u.id', '=', 'bc.user_id')
            ->leftJoin('user_avatars as ua', 'ua.user_id', '=', 'u.id')
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
                'ua.updated_at as avatar_updated_at',
            ])
            ->paginate($perPage);

        $items = array_map(function ($item): array {
            $avatarUrl = $this->buildAvatarUrl((int) $item->user_id, $item->avatar_updated_at ?? null, $item->avatar ?? null);

            return [
                'id' => (int) $item->id,
                'book_id' => (int) $item->book_id,
                'user_id' => (int) $item->user_id,
                'parent_id' => $item->parent_id !== null ? (int) $item->parent_id : null,
                'content' => $item->content,
                'likes_count' => (int) $item->likes_count,
                'is_edited' => (bool) $item->is_edited,
                'created_at' => $this->asIsoString($item->created_at),
                'updated_at' => $this->asIsoString($item->updated_at),
                'created_at_human' => $this->asHumanString($item->created_at),
                'updated_at_human' => $this->asHumanString($item->updated_at),
                'user' => [
                    'id' => (int) $item->user_id,
                    'name' => trim(($item->firstname ?? '').' '.($item->lastname ?? '')),
                    'firstname' => $item->firstname,
                    'lastname' => $item->lastname,
                    'profile_image_url' => $avatarUrl,
                    'avatar' => $avatarUrl,
                    'avatar_url' => $avatarUrl,
                    'photo' => $avatarUrl,
                    'photo_url' => $avatarUrl,
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
        if (!$this->isBookReviewable($book)) {
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
            ->leftJoin('user_avatars as ua', 'ua.user_id', '=', 'u.id')
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
                'ua.updated_at as avatar_updated_at',
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
                'created_at' => $this->asIsoString($comment->created_at),
                'updated_at' => $this->asIsoString($comment->updated_at),
                'created_at_human' => $this->asHumanString($comment->created_at),
                'updated_at_human' => $this->asHumanString($comment->updated_at),
                'user' => (function () use ($comment): array {
                    $avatarUrl = $this->buildAvatarUrl((int) $comment->user_id, $comment->avatar_updated_at ?? null, $comment->avatar ?? null);

                    return [
                    'id' => (int) $comment->user_id,
                    'name' => trim(($comment->firstname ?? '').' '.($comment->lastname ?? '')),
                    'firstname' => $comment->firstname,
                    'lastname' => $comment->lastname,
                    'profile_image_url' => $avatarUrl,
                    'avatar' => $avatarUrl,
                    'avatar_url' => $avatarUrl,
                    'photo' => $avatarUrl,
                    'photo_url' => $avatarUrl,
                    ];
                })(),
            ],
        ], 201);
    }

    public function listReviews(Request $request, Book $book): JsonResponse
    {
        if (!$this->isBookReviewable($book)) {
            return response()->json([
                'success' => false,
                'message' => 'Comments are available only for approved books.',
            ], 404);
        }

        $user = $this->resolveApiUser($request);
        $perPage = max(1, min((int) $request->query('per_page', 10), 100));
        $sort = $request->query('sort', 'newest');

        if (!in_array($sort, ['newest', 'top'], true)) {
            $sort = 'newest';
        }

        $query = DB::table('book_comments as bc')
            ->leftJoin('users as u', 'u.id', '=', 'bc.user_id')
            ->leftJoin('book_comment_likes as my_like', function ($join) use ($user) {
                $join->on('my_like.book_comment_id', '=', 'bc.id');

                if ($user) {
                    $join->where('my_like.user_id', '=', $user->id);
                } else {
                    $join->whereRaw('1 = 0');
                }
            })
            ->where('bc.book_id', $book->id)
            ->whereNull('bc.parent_id')
            ->select([
                'bc.id',
                'bc.book_id',
                'bc.user_id',
                'bc.content',
                'bc.rating',
                'bc.likes_count',
                'bc.created_at',
                'bc.updated_at',
                'u.firstname',
                'u.lastname',
                'u.avatar',
                DB::raw('(SELECT COUNT(*) FROM book_comments as replies WHERE replies.parent_id = bc.id) as replies_count'),
                DB::raw('CASE WHEN my_like.id IS NULL THEN 0 ELSE 1 END as liked_by_me'),
            ]);

        if ($sort === 'top') {
            $query
                ->orderByDesc('bc.rating')
                ->orderByDesc('bc.likes_count')
                ->orderByDesc('bc.created_at');
        } else {
            $query->orderByDesc('bc.created_at');
        }

        $reviews = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Comments retrieved successfully.',
            'data' => array_map(
                fn ($item): array => $this->mapReviewRow($item, $user),
                $reviews->items()
            ),
            'meta' => [
                'current_page' => $reviews->currentPage(),
                'last_page' => $reviews->lastPage(),
                'per_page' => $reviews->perPage(),
                'total' => $reviews->total(),
            ],
        ]);
    }

    public function createReview(Request $request, Book $book): JsonResponse
    {
        if (!$this->isBookReviewable($book)) {
            return response()->json([
                'success' => false,
                'message' => 'You can comment only on approved books.',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'text' => ['required', 'string', 'min:1', 'max:2000'],
            'rating' => ['required', 'integer', 'between:1,5'],
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors()->toArray());
        }

        $existingReview = DB::table('book_comments')
            ->where('book_id', $book->id)
            ->where('user_id', $request->user()->id)
            ->whereNull('parent_id')
            ->exists();

        if ($existingReview) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => [
                    'review' => ['You have already reviewed this book.'],
                ],
            ], 422);
        }

        $validated = $validator->validated();
        $now = now();
        $id = DB::table('book_comments')->insertGetId([
            'book_id' => $book->id,
            'user_id' => $request->user()->id,
            'parent_id' => null,
            'content' => trim((string) $validated['text']),
            'rating' => (int) $validated['rating'],
            'likes_count' => 0,
            'is_edited' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->syncBookRating($book->id, $request->user()->id, (int) $validated['rating']);

        $review = $this->findReviewRowById($id, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Comment created successfully.',
            'data' => $this->mapReviewRow($review, $request->user()),
        ], 201);
    }

    public function updateReview(Request $request, int $id): JsonResponse
    {
        $review = $this->findReviewRecord($id);

        if (!$review) {
            return response()->json([
                'success' => false,
                'message' => 'Review not found.',
            ], 404);
        }

        if ((int) $review->user_id !== (int) $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'You are not allowed to update this review.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'text' => ['sometimes', 'required', 'string', 'min:1', 'max:2000'],
            'rating' => ['sometimes', 'required', 'integer', 'between:1,5'],
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors()->toArray());
        }

        $validated = $validator->validated();
        if ($validated === []) {
            return $this->validationErrorResponse([
                'text' => ['At least one field must be provided.'],
            ]);
        }

        $update = ['updated_at' => now()];

        if (array_key_exists('text', $validated)) {
            $update['content'] = trim((string) $validated['text']);
            $update['is_edited'] = true;
        }

        if (array_key_exists('rating', $validated)) {
            $update['rating'] = (int) $validated['rating'];
        }

        DB::table('book_comments')
            ->where('id', $id)
            ->update($update);

        if (array_key_exists('rating', $validated)) {
            $this->syncBookRating((int) $review->book_id, (int) $review->user_id, (int) $validated['rating']);
        }

        $updatedReview = $this->findReviewRecord($id);

        return response()->json([
            'success' => true,
            'message' => 'Comment updated successfully.',
            'data' => [
                'id' => (int) $updatedReview->id,
                'text' => $updatedReview->content,
                'rating' => $updatedReview->rating !== null ? (int) $updatedReview->rating : null,
                'updated_at' => $this->asIsoString($updatedReview->updated_at),
            ],
        ]);
    }

    public function deleteReview(Request $request, int $id): JsonResponse
    {
        $review = $this->findReviewRecord($id);

        if (!$review) {
            return response()->json([
                'success' => false,
                'message' => 'Review not found.',
            ], 404);
        }

        if ((int) $review->user_id !== (int) $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'You are not allowed to delete this review.',
            ], 403);
        }

        DB::transaction(function () use ($review): void {
            DB::table('book_comments')->where('id', $review->id)->delete();
            DB::table('book_ratings')
                ->where('book_id', $review->book_id)
                ->where('user_id', $review->user_id)
                ->delete();

            $this->refreshBookAverageRating((int) $review->book_id);
        });

        return response()->json([
            'success' => true,
            'message' => 'Comment deleted successfully.',
        ]);
    }

    public function likeReview(Request $request, int $id): JsonResponse
    {
        $review = $this->findReviewRecord($id);

        if (!$review) {
            return response()->json([
                'success' => false,
                'message' => 'Review not found.',
            ], 404);
        }

        $inserted = false;

        DB::transaction(function () use ($id, $request, &$inserted): void {
            $inserted = DB::table('book_comment_likes')->insertOrIgnore([
                [
                    'book_comment_id' => $id,
                    'user_id' => $request->user()->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]) > 0;

            if ($inserted) {
                DB::table('book_comments')
                    ->where('id', $id)
                    ->increment('likes_count');
            }
        });

        $likes = (int) DB::table('book_comments')->where('id', $id)->value('likes_count');

        return response()->json([
            'success' => true,
            'message' => 'Comment liked.',
            'data' => [
                'id' => (int) $id,
                'likes' => $likes,
                'liked_by_me' => true,
            ],
        ]);
    }

    public function unlikeReview(Request $request, int $id): JsonResponse
    {
        $review = $this->findReviewRecord($id);

        if (!$review) {
            return response()->json([
                'success' => false,
                'message' => 'Review not found.',
            ], 404);
        }

        $deleted = 0;

        DB::transaction(function () use ($id, $request, &$deleted): void {
            $deleted = DB::table('book_comment_likes')
                ->where('book_comment_id', $id)
                ->where('user_id', $request->user()->id)
                ->delete();

            if ($deleted > 0) {
                DB::table('book_comments')
                    ->where('id', $id)
                    ->where('likes_count', '>', 0)
                    ->decrement('likes_count');
            }
        });

        $likes = (int) DB::table('book_comments')->where('id', $id)->value('likes_count');

        return response()->json([
            'success' => true,
            'message' => 'Comment unliked.',
            'data' => [
                'id' => (int) $id,
                'likes' => $likes,
                'liked_by_me' => false,
            ],
        ]);
    }

    public function ratings(Request $request, Book $book): JsonResponse
    {
        if (!$this->isBookReviewable($book)) {
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
        if (!$this->isBookReviewable($book)) {
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

    private function isBookReviewable(Book $book): bool
    {
        return $book->status === 'approved';
    }

    private function resolveApiUser(Request $request): ?User
    {
        $user = $request->user();

        if ($user instanceof User) {
            return $user;
        }

        $sanctumUser = auth('sanctum')->user();

        return $sanctumUser instanceof User ? $sanctumUser : null;
    }

    private function validationErrorResponse(array $errors): JsonResponse
    {
        return response()->json([
            'message' => 'Validation error',
            'errors' => $errors,
        ], 422);
    }

    private function findReviewRecord(int $id): ?object
    {
        return DB::table('book_comments')
            ->where('id', $id)
            ->whereNull('parent_id')
            ->first();
    }

    private function findReviewRowById(int $id, ?User $user): ?object
    {
        return DB::table('book_comments as bc')
            ->leftJoin('users as u', 'u.id', '=', 'bc.user_id')
            ->leftJoin('book_comment_likes as my_like', function ($join) use ($user) {
                $join->on('my_like.book_comment_id', '=', 'bc.id');

                if ($user) {
                    $join->where('my_like.user_id', '=', $user->id);
                } else {
                    $join->whereRaw('1 = 0');
                }
            })
            ->where('bc.id', $id)
            ->whereNull('bc.parent_id')
            ->select([
                'bc.id',
                'bc.book_id',
                'bc.user_id',
                'bc.content',
                'bc.rating',
                'bc.likes_count',
                'bc.created_at',
                'bc.updated_at',
                'u.firstname',
                'u.lastname',
                'u.avatar',
                DB::raw('(SELECT COUNT(*) FROM book_comments as replies WHERE replies.parent_id = bc.id) as replies_count'),
                DB::raw('CASE WHEN my_like.id IS NULL THEN 0 ELSE 1 END as liked_by_me'),
            ])
            ->first();
    }

    private function mapReviewRow(object $item, ?User $viewer): array
    {
        $ownerId = (int) $item->user_id;

        return [
            'id' => (int) $item->id,
            'user' => [
                'id' => $ownerId,
                'name' => trim(($item->firstname ?? '').' '.($item->lastname ?? '')),
                'profile_image_url' => $this->buildAvatarUrl($ownerId, $item->avatar_updated_at ?? null, $item->avatar ?? null),
                'avatar' => $this->buildAvatarUrl($ownerId, $item->avatar_updated_at ?? null, $item->avatar ?? null),
                'avatar_url' => $this->buildAvatarUrl($ownerId, $item->avatar_updated_at ?? null, $item->avatar ?? null),
                'photo' => $this->buildAvatarUrl($ownerId, $item->avatar_updated_at ?? null, $item->avatar ?? null),
                'photo_url' => $this->buildAvatarUrl($ownerId, $item->avatar_updated_at ?? null, $item->avatar ?? null),
            ],
            'text' => $item->content,
            'rating' => $item->rating !== null ? (int) $item->rating : null,
            'likes' => (int) $item->likes_count,
            'replies' => (int) ($item->replies_count ?? 0),
            'created_at' => $this->asIsoString($item->created_at),
            'updated_at' => $this->asIsoString($item->updated_at),
            'created_at_human' => $this->asHumanString($item->created_at),
            'updated_at_human' => $this->asHumanString($item->updated_at),
            'liked_by_me' => (bool) ($item->liked_by_me ?? false),
            'can_edit' => $viewer !== null && (int) $viewer->id === $ownerId,
            'can_delete' => $viewer !== null && (int) $viewer->id === $ownerId,
        ];
    }

    private function resolveAvatarUrl(?string $avatar): ?string
    {
        $value = trim((string) (PublicImage::normalize($avatar, 'avatars')['url'] ?? ''));

        if ($value === '') {
            return null;
        }

        if (preg_match('/^(https?:|data:)/i', $value)) {
            return $value;
        }

        return '/'.ltrim($value, '/');
    }

    private function buildAvatarUrl(int $userId, $updatedAt = null, ?string $fallbackAvatar = null): ?string
    {
        if ($updatedAt) {
            $version = Carbon::parse($updatedAt)->timestamp;

            return route('avatars.show', ['userId' => $userId, 'v' => $version]);
        }

        return $this->resolveAvatarUrl($fallbackAvatar);
    }

    private function asIsoString($value): ?string
    {
        if ($value === null) {
            return null;
        }

        return Carbon::parse($value)->toIso8601String();
    }

    private function asHumanString($value): ?string
    {
        if ($value === null) {
            return null;
        }

        return Carbon::parse($value)->diffForHumans();
    }

    private function syncBookRating(int $bookId, int $userId, int $rating): void
    {
        DB::table('book_ratings')->updateOrInsert(
            [
                'book_id' => $bookId,
                'user_id' => $userId,
            ],
            [
                'rating' => $rating,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $this->refreshBookAverageRating($bookId);
    }

    private function refreshBookAverageRating(int $bookId): void
    {
        $aggregate = DB::table('book_ratings')
            ->where('book_id', $bookId)
            ->selectRaw('COALESCE(AVG(rating), 0) as average_rating')
            ->first();

        DB::table('books')
            ->where('id', $bookId)
            ->update([
                'average_rating' => round((float) ($aggregate->average_rating ?? 0), 2),
                'updated_at' => now(),
            ]);
    }
}
