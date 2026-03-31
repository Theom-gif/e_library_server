<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FavoriteAuthor;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuthorFollowController extends Controller
{
    public function store(Request $request, int $authorId): JsonResponse
    {
        $user = $request->user();
        $author = $this->findAuthor($authorId);

        if (!$author) {
            return response()->json([
                'success' => false,
                'message' => 'Author not found.',
            ], 404);
        }

        FavoriteAuthor::query()->firstOrCreate([
            'user_id' => (int) $user->id,
            'author_id' => $author->id,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'author_id' => $author->id,
                'is_following' => true,
                'followers_count' => $this->followersCount($author->id),
            ],
        ]);
    }

    public function destroy(Request $request, int $authorId): JsonResponse
    {
        $user = $request->user();
        $author = $this->findAuthor($authorId);

        if (!$author) {
            return response()->json([
                'success' => false,
                'message' => 'Author not found.',
            ], 404);
        }

        FavoriteAuthor::query()
            ->where('user_id', (int) $user->id)
            ->where('author_id', $author->id)
            ->delete();

        return response()->json([
            'success' => true,
            'data' => [
                'author_id' => $author->id,
                'is_following' => false,
                'followers_count' => $this->followersCount($author->id),
            ],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $perPage = (int) ($validated['per_page'] ?? 20);

        $authors = User::query()
            ->with(['avatarImage'])
            ->select('users.*')
            ->join('favorite_authors', function ($join) use ($user) {
                $join->on('favorite_authors.author_id', '=', 'users.id')
                    ->where('favorite_authors.user_id', '=', (int) $user->id);
            })
            ->leftJoinSub(
                DB::table('favorite_authors')
                    ->selectRaw('author_id, COUNT(*) as followers_count')
                    ->groupBy('author_id'),
                'follower_counts',
                fn ($join) => $join->on('follower_counts.author_id', '=', 'users.id')
            )
            ->addSelect(DB::raw('COALESCE(follower_counts.followers_count, 0) as followers_count'))
            ->addSelect(DB::raw('1 as is_following'))
            ->where('users.role_id', 2)
            ->orderByDesc('favorite_authors.created_at')
            ->paginate($perPage);

        $data = $authors->getCollection()
            ->map(fn (User $author): array => $this->transformAuthor($author))
            ->values();

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

    private function findAuthor(int $authorId): ?User
    {
        return User::query()
            ->where('id', $authorId)
            ->where('role_id', 2)
            ->first();
    }

    private function followersCount(int $authorId): int
    {
        return (int) FavoriteAuthor::query()
            ->where('author_id', $authorId)
            ->count();
    }

    /**
     * @return array<string, mixed>
     */
    private function transformAuthor(User $author): array
    {
        $fullName = trim(($author->firstname ?? '').' '.($author->lastname ?? ''));
        $avatar = $this->resolveAvatarData($author);

        return [
            'id' => $author->id,
            'name' => $fullName !== '' ? $fullName : 'Unknown',
            'profile_image_url' => $avatar['url'],
            'photo' => $avatar['url'],
            'photo_url' => $avatar['url'],
            'avatar_url' => $avatar['url'],
            'followers_count' => (int) ($author->followers_count ?? 0),
            'is_following' => true,
        ];
    }

    /**
     * @return array{path: ?string, url: ?string}
     */
    private function resolveAvatarData(User $author): array
    {
        return $author->resolveProfileImage();
    }
}
