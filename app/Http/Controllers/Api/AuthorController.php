<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
                $authorQuery->where('firstname', 'like', '%'.$search.'%')
                    ->orWhere('lastname', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhere('bio', 'like', '%'.$search.'%')
                    ->orWhereRaw("CONCAT(COALESCE(firstname, ''), ' ', COALESCE(lastname, '')) LIKE ?", ['%'.$search.'%']);
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
            'data' => $this->transformUser($author, $roleMap),
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
            'data' => $this->transformUser($author, $roleMap),
        ]);
    }

    private function baseAuthorQuery(int $roleId)
    {
        return User::query()
            ->with(['avatarImage'])
            ->select('users.*')
            ->selectSub(function ($subQuery) {
                $subQuery->from('books')
                    ->selectRaw('COUNT(*)')
                    ->where(function ($bookQuery) {
                        $bookQuery->whereColumn('books.author_id', 'users.id')
                            ->orWhere(function ($fallbackQuery) {
                                $fallbackQuery->whereNull('books.author_id')
                                    ->whereColumn('books.user_id', 'users.id');
                            });
                    });
            }, 'books_count')
            ->selectSub(function ($subQuery) {
                $subQuery->from('favorite_authors')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('favorite_authors.author_id', 'users.id');
            }, 'followers_count')
            ->selectSub(function ($subQuery) {
                $subQuery->from('book_ratings')
                    ->join('books', 'books.id', '=', 'book_ratings.book_id')
                    ->selectRaw('COALESCE(AVG(book_ratings.rating), 0)')
                    ->where(function ($bookQuery) {
                        $bookQuery->whereColumn('books.author_id', 'users.id')
                            ->orWhere(function ($fallbackQuery) {
                                $fallbackQuery->whereNull('books.author_id')
                                    ->whereColumn('books.user_id', 'users.id');
                            });
                    });
            }, 'avg_rating')
            ->where('role_id', $roleId);
    }

    private function transformUser(User $author, array $roleMap): array
    {
        $fullName = trim(($author->firstname ?? '').' '.($author->lastname ?? ''));
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

        if (Storage::disk('public')->exists($value)) {
            $storageUrl = Storage::disk('public')->url($value);

            return [
                'path' => $value,
                'url' => $this->toAbsoluteUrl($storageUrl),
            ];
        }

        return ['path' => null, 'url' => $this->toAbsoluteUrl($value)];
    }

    private function getRoleMap(): array
    {
        return DB::table('roles')
            ->pluck('name', 'id')
            ->map(static fn ($name) => (string) $name)
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
