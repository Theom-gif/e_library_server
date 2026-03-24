<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Requests\admin\RegistationAuthor;
use App\Models\User;
use App\Support\PublicImage;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    // --------------------------------------
    // Register User
    // --------------------------------------
    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            return $this->registerUser($request->validated());
        } catch (\Exception $e) {
            return $this->errorResponse('Registration failed', $e->getMessage(), 500);
        }
    }

    // --------------------------------------
    // Register Author
    // --------------------------------------
    public function authorRegister(RegistationAuthor $request): JsonResponse
    {
        try {
            return $this->registerUser($request->validated());
        } catch (\Exception $e) {
            return $this->errorResponse('Registration failed', $e->getMessage(), 500);
        }
    }

    // --------------------------------------
    // Login
    // --------------------------------------
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return $this->errorResponse('Invalid credentials', 'Email or password is incorrect', 401);
        }
        $user->tokens()->delete();
        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->successResponse([
            'user'  => $user,
            'token' => $token,
        ], 'Login successful', 200);
    }

    // --------------------------------------
    // Logout
    // --------------------------------------
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->successResponse(null, 'Logout successful', 200);
    }

    // --------------------------------------
    // Request Password Reset
    // --------------------------------------
    public function requestPasswordReset(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', $validator->errors(), 422);
        }

        $user = User::where('email', $request->email)->first();
        $token = Str::random(64);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            [
                'email' => $user->email,
                'token' => Hash::make($token),
                'created_at' => now(),
            ]
        );

        // TODO: Send $token via email

        return $this->successResponse(null, 'Password reset link sent to your email', 200);
    }

    // --------------------------------------
    // Reset Password
    // --------------------------------------
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $resetRecord = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (!$resetRecord || !Hash::check($request->token, $resetRecord->token)) {
            return $this->errorResponse('Invalid token', 'The reset token is invalid or expired', 400);
        }

        if (now()->diffInHours($resetRecord->created_at) > 1) {
            return $this->errorResponse('Expired token', 'The reset token has expired', 400);
        }

        $user = User::where('email', $request->email)->first();
        $user->update(['password' => $request->password]);

        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return $this->successResponse(null, 'Password reset successfully', 200);
    }

    // --------------------------------------
    // Get Current User (Dashboard API)
    // --------------------------------------
    public function getCurrentUser(Request $request): JsonResponse
    {
        if ($request->is('api/me') || $request->is('api/me/profile')) {
            return $this->successResponse(
                $this->buildProfilePayload($request->user()),
                'Profile retrieved successfully',
                200
            );
        }

        return $this->successResponse(
            $request->user(),
            'User retrieved successfully',
            200
        );
    }

    // --------------------------------------
    // Update Profile
    // --------------------------------------
     public function updateProfile(UpdateProfileRequest $request): JsonResponse
{
    try {
        $payload = [];

        // Step 1: collect text fields
        foreach (['firstname', 'lastname', 'bio', 'facebook_url'] as $field) {
            if ($request->exists($field)) {
                $payload[$field] = $request->input($field);
            }
        }

        Log::info('updateProfile | text payload', $payload);
        Log::info('updateProfile | has avatar_file', ['has' => $request->hasFile('avatar_file')]);

        // Step 2: handle avatar BEFORE the empty check
        // Case 1 — local file upload
        if ($request->hasFile('avatar_file')) {
            $encrypted = $this->storeAvatarFile($request->file('avatar_file'));

            if (!$encrypted) {
                return $this->errorResponse(
                    'Avatar upload failed',
                    'Please ensure the file is a valid image under 5MB.',
                    422
                );
            }

            $payload['avatar'] = $encrypted;
            Log::info('updateProfile | stored local file', ['avatar' => $encrypted]);

        // Case 2 — external HTTPS URL
        } elseif ($request->filled('avatar')) {
            $avatarInput = $request->input('avatar');

            if (filter_var($avatarInput, FILTER_VALIDATE_URL)) {
                $normalized        = PublicImage::normalize($avatarInput, 'avatars');
                $payload['avatar'] = $normalized['url'] ?? $avatarInput;
            } else {
                $payload['avatar'] = $avatarInput;
            }

            Log::info('updateProfile | using avatar URL', ['avatar' => $payload['avatar']]);
        }

        Log::info('updateProfile | final payload', $payload);

        // Step 3: NOW check empty — after avatar has been added
        if (empty($payload)) {
            return $this->errorResponse(
                'No fields to update',
                'Please provide at least one field to update.',
                422
            );
        }

        $user    = $request->user();
        $updated = $user->fill($payload)->save();

        Log::info('updateProfile | save result', [
            'updated' => $updated,
            'user_id' => $user->id,
        ]);

        if (!$updated) {
            return $this->errorResponse('Failed to update profile', 'Please try again.', 500);
        }

        return $this->successResponse(
            $this->buildProfilePayload($user->fresh()),
            'Profile updated successfully',
            200
        );

    } catch (\Illuminate\Database\QueryException $e) {
        Log::error('updateProfile | DB error', ['error' => $e->getMessage()]);
        return $this->errorResponse('Database error', $e->getMessage(), 500);

    } catch (\Exception $e) {
        Log::error('updateProfile | Unexpected error', ['error' => $e->getMessage()]);
        return $this->errorResponse('Unexpected error', $e->getMessage(), 500);
    }
}


    // --------------------------------------
    // Change Password
    // --------------------------------------
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        if (!Hash::check($request->current_password, $request->user()->password)) {
            return $this->errorResponse('Current password is incorrect', null, 401);
        }

        $request->user()->update(['password' => $request->new_password]);

        return $this->successResponse(null, 'Password changed successfully', 200);
    }

      // --------------------------------------
    // Helper Methods
    // --------------------------------------
    private function successResponse($data, string $message, int $code = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ], $code);
    }

    private function errorResponse(string $message, $errors = null, int $code = 400): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors'  => $errors,
        ], $code);
    }

    /**
     * @param array<string, mixed> $validated
     */
    private function registerUser(array $validated): JsonResponse
    {
        $user = User::create([
            'firstname' => $validated['firstname'],
            'lastname' => $validated['lastname'],
            'email' => $validated['email'],
            'password' => $validated['password'], // auto-hashed by model
            'role_id' => $validated['role_id'],
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->successResponse([
            'user' => $user,
            'token' => $token,
        ], 'User registered successfully', 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildProfilePayload(User $user): array
    {
        $favoritesCount = DB::table('favorites')
            ->where('user_id', $user->id)
            ->count();

        $downloadsCount = DB::table('offline_downloads')
            ->where('user_id', $user->id)
            ->count();

        $booksReadCount = DB::table('reading_sessions')
            ->where('user_id', $user->id)
            ->where('duration_seconds', '>', 0)
            ->distinct('book_id')
            ->count('book_id');

        $totalReadingSeconds = (int) DB::table('reading_activity_daily')
            ->where('user_id', $user->id)
            ->sum('seconds_read');

        $readingDaysCount = DB::table('reading_activity_daily')
            ->where('user_id', $user->id)
            ->where('seconds_read', '>', 0)
            ->count();

        $userData = [
            'id' => $user->id,
            'role_id' => $user->role_id,
            'firstname' => $user->firstname,
            'lastname' => $user->lastname,
            'first_name' => $user->firstname,
            'last_name' => $user->lastname,
            'full_name' => trim(($user->firstname ?? '').' '.($user->lastname ?? '')),
            'email' => $user->email,
            'bio' => $user->bio,
            'facebook_url' => $user->facebook_url,
            'avatar' => $user->avatar,
            'avatar_url' => $this->resolveAvatarUrl($user->avatar),
            'created_at' => $user->created_at?->toIso8601String(),
            'updated_at' => $user->updated_at?->toIso8601String(),
            'member_since' => $user->created_at?->toDateString(),
        ];

        return [
            'user' => $userData,
            'stats' => [
                'favorites_count' => (int) $favoritesCount,
                'downloads_count' => (int) $downloadsCount,
                'books_read_count' => (int) $booksReadCount,
                'reading_days_count' => (int) $readingDaysCount,
                'total_reading_seconds' => $totalReadingSeconds,
                'total_reading_minutes' => (int) round($totalReadingSeconds / 60),
            ],
        ];
    }

    private function resolveAvatarUrl(?string $avatar): ?string
    {
        $value = trim((string) $avatar);

        if ($value === '') {
            return null;
        }

        return PublicImage::normalize($value, 'avatars')['url'] ?? null;
    }

    /**
     * Store an uploaded avatar file into the `public` disk under `avatars/`.
     * Returns the stored path or null on failure.
     */
    private function storeAvatarFile($file): ?string
    {
        try {
            $path = $file->store('avatars', 'public');
            return $path;
        } catch (\Exception $e) {
            Log::error('storeAvatarFile error', ['error' => $e->getMessage()]);
            return null;
        }
    }

}
