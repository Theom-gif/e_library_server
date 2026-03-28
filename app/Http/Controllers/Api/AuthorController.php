<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserAvatar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuthorController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:255',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $search = trim((string) ($validated['q'] ?? ''));
        $perPage = (int) ($validated['per_page'] ?? 60);

        $query = User::query()
            ->where('role_id', 2)
            ->with(['avatarImage'])
            ->select('users.*')
            ->selectSub(function ($subQuery) {
                $subQuery->from('books')
                    ->selectRaw('COUNT(DISTINCT books.id)')
                    ->whereColumn('books.user_id', 'users.id');
            }, 'books_count')
            ->selectSub(function ($subQuery) {
                $subQuery->from('favorite_authors')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('favorite_authors.author_id', 'users.id');
            }, 'followers_count')
            ->selectSub(function ($subQuery) {
                $subQuery->from('book_ratings')
                    ->join('books', 'books.id', '=', 'book_ratings.book_id')
                    ->selectRaw('AVG(book_ratings.rating)')
                    ->whereColumn('books.user_id', 'users.id');
            }, 'avg_rating');

        if ($search !== '') {
            $query->where(function ($authorQuery) use ($search) {
                $authorQuery->where('firstname', 'like', '%'.$search.'%')
                    ->orWhere('lastname', 'like', '%'.$search.'%')
                    ->orWhereRaw("CONCAT(COALESCE(firstname, ''), ' ', COALESCE(lastname, '')) LIKE ?", ['%'.$search.'%'])
                    ->orWhere('bio', 'like', '%'.$search.'%');
            });
        }

        $authors = $query
            ->orderByRaw("COALESCE(firstname, '') ASC")
            ->paginate($perPage);

        $data = $authors->getCollection()->map(function (User $author) {
            return $this->transformAuthor($author);
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
        $author = User::query()
            ->where('role_id', 2)
            ->with(['avatarImage'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $this->transformAuthor($author),
        ]);
    }

    public function showByName(string $name): JsonResponse
    {
        $author = User::query()
            ->where('role_id', 2)
            ->with(['avatarImage'])
            ->whereRaw("LOWER(CONCAT(COALESCE(firstname, ''), ' ', COALESCE(lastname, ''))) = ?", [mb_strtolower(trim($name))])
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $this->transformAuthor($author),
        ]);
    }

    private function transformAuthor(User $author): array
    {
        $fullName = trim(($author->firstname ?? '').' '.($author->lastname ?? ''));
        $avatarUrl = $this->resolvePhotoUrl($author);

        return [
            'id' => $author->id,
            'name' => $fullName !== '' ? $fullName : ($author->firstname ?: $author->lastname ?: 'Unknown'),
            'bio' => $author->bio,
            'photo' => $avatarUrl,
            'avatar' => $avatarUrl,
            'image_url' => $avatarUrl,
            'followers' => (int) ($author->followers_count ?? 0),
            'avg_rating' => round((float) ($author->avg_rating ?? 0), 2),
            'average_rating' => round((float) ($author->avg_rating ?? 0), 2),
            'books_count' => (int) ($author->books_count ?? 0),
            'book_count' => (int) ($author->books_count ?? 0),
        ];
    }

    private function resolvePhotoUrl(User $author): ?string
    {
        if ($author->avatarImage) {
            return route('avatars.show', [
                'userId' => $author->id,
                'v' => optional($author->avatarImage->updated_at)->timestamp,
            ], false);
        }

        $avatar = trim((string) $author->avatar);
        if ($avatar === '') {
            return null;
        }

        if (preg_match('/^(https?:|data:)/i', $avatar)) {
            return $avatar;
        }

        return str_starts_with($avatar, '/')
            ? $avatar
            : '/'.ltrim($avatar, '/');
    }
}
