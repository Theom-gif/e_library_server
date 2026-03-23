<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AdminUserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = $this->resolveSearchKeyword($request);
        $roleFilter = strtolower(trim((string) $request->query('role', '')));
        $perPage = max(1, min((int) $request->query('per_page', 25), 100));

        $roleMap = $this->getRoleMap();

        $query = User::query()
            ->select(['id', 'role_id', 'firstname', 'lastname', 'email', 'created_at']);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('firstname', 'like', '%'.$search.'%')
                    ->orWhere('lastname', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%');

                if (is_numeric($search)) {
                    $q->orWhere('id', (int) $search);
                }
            });
        }

        if ($roleFilter !== '' && $roleFilter !== 'all') {
            $roleId = $this->resolveRoleId($roleFilter, $roleMap);
            if ($roleId === null) {
                return response()->json([
                    'success' => true,
                    'message' => 'Users retrieved successfully.',
                    'data' => [],
                    'meta' => [
                        'current_page' => 1,
                        'last_page' => 1,
                        'per_page' => $perPage,
                        'total' => 0,
                    ],
                ]);
            }

            $query->where('role_id', $roleId);
        }

        $users = $query->orderByDesc('id')->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Users retrieved successfully.',
            'data' => array_map(
                fn (User $user) => $this->transformUser($user, $roleMap),
                $users->items()
            ),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    public function show(User $user): JsonResponse
    {
        $roleMap = $this->getRoleMap();

        return response()->json([
            'success' => true,
            'message' => 'User retrieved successfully.',
            'data' => $this->transformUser($user, $roleMap),
        ]);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $roleMap = $this->getRoleMap();
        $input = $this->normalizeUpdateInput($request);

        $validator = Validator::make($input, [
            'first_name' => 'sometimes|required|string|max:255',
            'last_name' => 'sometimes|required|string|max:255',
            'email' => [
                'sometimes',
                'required',
                'email',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'role_id' => 'sometimes|required|integer|exists:roles,id',
            'role' => 'sometimes|required|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $roleId = null;

        if (array_key_exists('role_id', $validated)) {
            $roleId = (int) $validated['role_id'];
        } elseif (array_key_exists('role', $validated)) {
            $resolvedRoleId = $this->resolveRoleId(
                strtolower(trim((string) $validated['role'])),
                $roleMap
            );

            if ($resolvedRoleId === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid role value.',
                    'errors' => [
                        'role' => ['Role must be Admin, Author, or User.'],
                    ],
                ], 422);
            }

            $roleId = $resolvedRoleId;
        }

        $updates = [];
        if (array_key_exists('first_name', $validated)) {
            $updates['firstname'] = $validated['first_name'];
        }
        if (array_key_exists('last_name', $validated)) {
            $updates['lastname'] = $validated['last_name'];
        }
        if (array_key_exists('email', $validated)) {
            $updates['email'] = $validated['email'];
        }
        if ($roleId !== null) {
            $updates['role_id'] = $roleId;
        }

        if (!empty($updates)) {
            $user->update($updates);
            $user->refresh();
        }

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully.',
            'data' => $this->transformUser($user, $roleMap),
        ]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        if ((int) $request->user()->id === (int) $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot delete your own admin account.',
            ], 422);
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully.',
        ]);
    }

    private function transformUser(User $user, array $roleMap): array
    {
        return [
            'id' => $user->id,
            'role' => $roleMap[$user->role_id] ?? $this->fallbackRoleName((int) $user->role_id),
            'role_id' => $user->role_id,
            'first_name' => $user->firstname,
            'last_name' => $user->lastname,
            'email' => $user->email,
            'created_at' => optional($user->created_at)->toDateTimeString(),
        ];
    }

    private function normalizeUpdateInput(Request $request): array
    {
        return [
            'first_name' => $request->input('first_name', $request->input('firstname', $request->input('firstName'))),
            'last_name' => $request->input('last_name', $request->input('lastname', $request->input('lastName'))),
            'email' => $request->input('email'),
            'role' => $request->input('role', $request->input('role_name')),
            'role_id' => $request->input('role_id', $request->input('roleId')),
        ];
    }

    private function getRoleMap(): array
    {
        return DB::table('roles')
            ->pluck('name', 'id')
            ->map(static fn ($name) => (string) $name)
            ->all();
    }

    private function resolveSearchKeyword(Request $request): string
    {
        foreach (['search', 'q', 'query', 'keyword'] as $key) {
            $value = trim((string) $request->query($key, ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function resolveRoleId(string $roleFilter, array $roleMap): ?int
    {
        if (is_numeric($roleFilter)) {
            return (int) $roleFilter;
        }

        foreach ($roleMap as $id => $name) {
            if (strtolower(trim($name)) === $roleFilter) {
                return (int) $id;
            }
        }

        return match ($roleFilter) {
            'admin' => 1,
            'author' => 2,
            'user' => 3,
            default => null,
        };
    }

    private function fallbackRoleName(int $roleId): string
    {
        return match ($roleId) {
            1 => 'Admin',
            2 => 'Author',
            3 => 'User',
            default => 'Unknown',
        };
    }
}
