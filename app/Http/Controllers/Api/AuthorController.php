<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class AuthorController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:255',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'role_id' => 'nullable|integer',
            'role' => 'nullable|string|max:100',
            'role_name' => 'nullable|string|max:100',
        ]);

        $roleMap = $this->getRoleMap();
        $authorRoleId = $this->resolveAuthorRoleId($validated, $roleMap);

        if ($authorRoleId === null) {
            return response()->json([
                'success' => true,
                'data' => [],
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => (int) ($validated['per_page'] ?? 60),
                    'total' => 0,
                ],
            ]);
        }

        $search = trim((string) ($validated['q'] ?? ''));
        $perPage = (int) ($validated['per_page'] ?? 60);

        $query = $this->baseAuthorQuery($authorRoleId);

        if ($search !== '') {
            $query->where(function ($authorQuery) use ($search) {
                $authorQuery->where('firstname', 'like', '%' . $search . '%')
                    ->orWhere('lastname', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%')
                    ->orWhere('bio', 'like', '%' . $search . '%')
                    ->orWhereRaw("CONCAT(COALESCE(firstname, ''), ' ', COALESCE(lastname, '')) LIKE ?", ['%' . $search . '%']);
            });
        }

        $authors = $query
            ->orderByRaw("COALESCE(firstname, '') ASC")
            ->orderByRaw("COALESCE(lastname, '') ASC")
            ->paginate($perPage);

        $data = $authors->getCollection()->map(function (User $author) use ($roleMap): array {
            return $this->transformUser($author, $roleMap);
        })->values();

        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'current_page' => $authors->currentPage(),
                'last_page' => $authors->lastPage(),
                'per_page' => $authors->perPage(),
                'total' => $authors->total(),
            ],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $roleMap = $this->getRoleMap();
        $authorRoleId = $this->resolveRoleIdFromName('author', $roleMap) ?? 2;

        $author = $this->baseAuthorQuery($authorRoleId)
            ->where('users.id', $id)
            ->first();

        if (!$author) {
            return response()->json([
                'success' => false,
                'message' => 'Author not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->transformUserDetail($author, $roleMap),
        ]);
    }

    public function showByName(string $name): JsonResponse
    {
        $roleMap = $this->getRoleMap();
        $authorRoleId = $this->resolveRoleIdFromName('author', $roleMap) ?? 2;
        $needle = mb_strtolower(trim($name));

        $author = $this->baseAuthorQuery($authorRoleId)
            ->whereRaw("LOWER(CONCAT(COALESCE(firstname, ''), ' ', COALESCE(lastname, ''))) = ?", [$needle])
            ->first();

        if (!$author) {
            return response()->json([
                'success' => false,
                'message' => 'Author not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->transformUserDetail($author, $roleMap),
        ]);
    }

    private function baseAuthorQuery(int $roleId)
    {
        $ownerExpression = $this->booksOwnerExpression();

        $bookCounts = DB::table('books')
            ->selectRaw($ownerExpression.' as owner_id, COUNT(*) as books_count')
            ->when(Schema::hasColumn('books', 'status'), function ($query) {
                $query->where('status', 'approved');
            })
            ->groupByRaw($ownerExpression);

        $followerCounts = DB::table('favorite_authors')
            ->selectRaw('author_id, COUNT(*) as followers_count')
            ->groupBy('author_id');

        $ratingAverages = DB::table('book_ratings')
            ->join('books', 'books.id', '=', 'book_ratings.book_id')
            ->selectRaw($ownerExpression.' as owner_id, COALESCE(AVG(book_ratings.rating), 0) as avg_rating')
            ->when(Schema::hasColumn('books', 'status'), function ($query) {
                $query->where('books.status', 'approved');
            })
            ->groupByRaw($ownerExpression);

        return User::query()
            ->with(['avatarImage'])
            ->select('users.*')
            ->leftJoinSub($bookCounts, 'book_counts', function ($join) {
                $join->on('book_counts.owner_id', '=', 'users.id');
            })
            ->leftJoinSub($followerCounts, 'follower_counts', function ($join) {
                $join->on('follower_counts.author_id', '=', 'users.id');
            })
            ->leftJoinSub($ratingAverages, 'rating_averages', function ($join) {
                $join->on('rating_averages.owner_id', '=', 'users.id');
            })
            ->addSelect(DB::raw('COALESCE(book_counts.books_count, 0) as books_count'))
            ->addSelect(DB::raw('COALESCE(follower_counts.followers_count, 0) as followers_count'))
            ->addSelect(DB::raw('COALESCE(rating_averages.avg_rating, 0) as avg_rating'))
            ->where('role_id', $roleId);
    }

    private function booksOwnerExpression(): string
    {
        static $ownerExpression = null;

        if ($ownerExpression !== null) {
            return $ownerExpression;
        }

        $ownerExpression = Schema::hasColumn('books', 'author_id')
            ? 'COALESCE(books.author_id, books.user_id)'
            : 'books.user_id';

        return $ownerExpression;
    }

    private function transformUser(User $author, array $roleMap): array
    {
        $fullName = trim(($author->firstname ?? '') . ' ' . ($author->lastname ?? ''));
        $roleName = strtolower(trim((string) ($roleMap[$author->role_id] ?? 'author')));
        $avatar = $this->resolveAvatarData($author);
        $avgRating = round((float) ($author->avg_rating ?? 0), 2);

        return [
            'id' => $author->id,
            'name' => $fullName !== '' ? $fullName : 'Unknown',
            'firstname' => $author->firstname,
            'lastname' => $author->lastname,
            'role_id' => (int) $author->role_id,
            'role_name' => $roleName,
            'role' => $roleName,
            'bio' => $author->bio,
            'avatar' => $avatar['url'],
            'avatar_url' => $avatar['url'],
            'photo' => $avatar['url'],
            'photo_url' => $avatar['url'],
            'image_url' => $avatar['url'],
            'profile_photo_path' => $avatar['path'],
            'profile_photo_url' => $avatar['url'],
            'avatar_path' => $avatar['path'],
            'followers_count' => (int) ($author->followers_count ?? 0),
            'followers' => (int) ($author->followers_count ?? 0),
            'books_count' => (int) ($author->books_count ?? 0),
            'book_count' => (int) ($author->books_count ?? 0),
            'avg_rating' => $avgRating,
            'average_rating' => $avgRating,
        ];
    }

    private function transformUserDetail(User $author, array $roleMap): array
    {
        $payload = $this->transformUser($author, $roleMap);
        $books = $this->authorBooks((int) $author->id);

        $payload['books'] = $books;
        $payload['books_data'] = $books;

        return $payload;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function authorBooks(int $authorId): array
    {
        return $this->authorBooksQuery($authorId)
            ->with(['coverImage', 'author:id,firstname,lastname'])
            ->where('status', 'approved')
            ->latest('id')
            ->get()
            ->map(fn ($book): array => $this->transformAuthorBook($book))
            ->values()
            ->all();
    }

    private function authorBooksQuery(int $authorId)
    {
        return \App\Models\Book::query()
            ->where(function ($builder) use ($authorId) {
                if (Schema::hasColumn('books', 'author_id')) {
                    $builder->where('author_id', $authorId);

                    if (Schema::hasColumn('books', 'user_id')) {
                        $builder->orWhere(function ($fallback) use ($authorId) {
                            $fallback->whereNull('author_id')
                                ->where('user_id', $authorId);
                        });
                    }

                    return;
                }

                if (Schema::hasColumn('books', 'user_id')) {
                    $builder->where('user_id', $authorId);
                }
            });
    }

    /**
     * @return array<string, mixed>
     */
    private function transformAuthorBook(\App\Models\Book $book): array
    {
        $book->loadMissing(['coverImage', 'author']);
        $cover = $book->resolvedCoverAsset();
        $authorName = trim((string) ($book->author?->firstname.' '.$book->author?->lastname));
        if ($authorName === '') {
            $authorName = trim((string) $book->author_name) ?: 'Unknown';
        }

        return [
            'id' => $book->id,
            'title' => $book->title,
            'slug' => $book->slug,
            'description' => $book->description,
            'authorId' => $book->author_id ?? $book->user_id,
            'author_id' => $book->author_id ?? $book->user_id,
            'author_name' => $authorName,
            'author' => $authorName,
            'status' => (string) $book->status,
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

    private function resolveAvatarData(User $author): array
    {
        if ($author->avatarImage) {
            return [
                'path' => null,
                'url' => route('avatars.show', [
                    'userId' => $author->id,
                    'v' => optional($author->avatarImage->updated_at)->timestamp,
                ]),
            ];
        }

        $raw = trim((string) $author->avatar);
        if ($raw === '') {
            return ['path' => null, 'url' => null];
        }

        if (preg_match('/^(https?:|data:)/i', $raw)) {
            return ['path' => null, 'url' => $raw];
        }

        $normalized = $this->normalizeAsset($raw);

        return [
            'path' => $normalized['path'] ?? null,
            'url' => $normalized['url'] ?? null,
        ];
    }

    private function normalizeAsset(string $value): array
    {
        $value = trim($value);

        if ($value === '') {
            return ['path' => null, 'url' => null];
        }

        if (preg_match('/^(https?:|data:)/i', $value)) {
            return ['path' => null, 'url' => $value];
        }

        if (preg_match('/^(?:[A-Za-z]:[\\\\\\/]|\\\\\\\\)/', $value)) {
            return ['path' => null, 'url' => null];
        }

        return [
            'path' => $value,
            'url' => $this->toAbsoluteUrl(Storage::disk('public')->url($value)),
        ];
    }

    private function getRoleMap(): array
    {
        return DB::table('roles')
            ->pluck('name', 'id')
            ->map(static fn($name) => (string) $name)
            ->all();
    }

    private function resolveAuthorRoleId(array $validated, array $roleMap): ?int
    {
        $requestedRoleId = $validated['role_id'] ?? null;
        $requestedRole = strtolower(trim((string) ($validated['role'] ?? $validated['role_name'] ?? '')));

        if ($requestedRoleId !== null) {
            return ((int) $requestedRoleId === 2) ? 2 : null;
        }

        if ($requestedRole !== '') {
            if (in_array($requestedRole, ['author', 'authors'], true)) {
                return 2;
            }

            return null;
        }

        return 2;
    }

    private function resolveRoleIdFromName(string $roleName, array $roleMap): ?int
    {
        foreach ($roleMap as $id => $name) {
            if (strtolower(trim((string) $name)) === strtolower(trim($roleName))) {
                return (int) $id;
            }
        }

        return $roleName === 'author' ? 2 : null;
    }

    private function toAbsoluteUrl(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^(https?:|data:)/i', $value)) {
            return $value;
        }

        return url($value);
    }
}
