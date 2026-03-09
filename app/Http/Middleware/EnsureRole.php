<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    private const ROLE_MAP = [
        1 => 'admin',
        2 => 'author',
        3 => 'user',
    ];

    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $allowedRoles = array_map(
            static fn (string $role): string => strtolower(trim($role)),
            $roles
        );

        $userRole = self::ROLE_MAP[(int) $user->role_id] ?? null;

        if ($userRole === null || !in_array($userRole, $allowedRoles, true)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to perform this action.',
            ], 403);
        }

        return $next($request);
    }
}
