<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckTokenExpiry
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // If no user or no token, continue (or block if you want stricter)
        if (!$user || !$user->currentAccessToken()) {
            return $next($request);
        }

        $token = $user->currentAccessToken();

        // Check if token is older than 1 day.
        if ($token->created_at->lt(now()->subDay())) {
            // Delete expired token and require login again.
            $token->delete();

            return response()->json([
                'success' => false,
                'message' => 'Session expired. Please login again.',
            ], 401);
        }

        return $next($request);
    }
}
