<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Requests\ChangePasswordRequest;
use App\Models\User;
use App\Support\PublicImage;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProfileController extends Controller
{
    public function getCurrentUser(Request $request): JsonResponse
    {
        if ($request->is('api/me') || $request->is('api/me/profile')) {
            return $this->successResponse(
                $this->buildProfilePayload($request->user()),
                'Profile retrieved successfully',
                200
            );
        }

        return $this->successResponse($request->user(), 'User retrieved successfully', 200);
    }

    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        try {
            $payload = [];

            foreach (['firstname', 'lastname', 'bio', 'facebook_url'] as $field) {
                if ($request->exists($field)) {
                    $payload[$field] = $request->input($field);
                }
            }

            Log::info('updateProfile | text payload', $payload);
            Log::info('updateProfile | has avatar_file', ['has' => $request->hasFile('avatar_file')]);

            // Accept file uploads under either `avatar_file` or `avatar` (some frontends send `avatar` as file)
            if ($request->hasFile('avatar_file') || $request->hasFile('avatar')) {
                $fileField = $request->hasFile('avatar_file') ? 'avatar_file' : 'avatar';
                $file = $request->file($fileField);

                $encrypted = $this->storeAvatarFile($file);

                if (!$encrypted) {
                    return $this->errorResponse('Avatar upload failed', 'Please ensure the file is a valid image under 5MB.', 422);
                }

                $payload['avatar'] = $encrypted;
                Log::info('updateProfile | stored local file', ['field' => $fileField, 'avatar' => $encrypted]);

            } elseif ($request->filled('avatar')) {
                $avatarInput = $request->input('avatar');

                if (filter_var($avatarInput, FILTER_VALIDATE_URL)) {
                    $normalized        = PublicImage::normalize($avatarInput, 'avatars');
                    $payload['avatar'] = $normalized['url'] ?? $avatarInput;
                } else {
                    $payload['avatar'] = $avatarInput;
                }

                Log::info('updateProfile | using avatar URL/string', ['avatar' => $payload['avatar']]);
            }

            Log::info('updateProfile | final payload', $payload);

            if (empty($payload)) {
                return $this->errorResponse('No fields to update', 'Please provide at least one field to update.', 422);
            }

            $user    = $request->user();
            $updated = $user->fill($payload)->save();

            Log::info('updateProfile | save result', ['updated' => $updated, 'user_id' => $user->id]);

            if (!$updated) {
                return $this->errorResponse('Failed to update profile', 'Please try again.', 500);
            }

            return $this->successResponse($this->buildProfilePayload($user->fresh()), 'Profile updated successfully', 200);

        } catch (\Illuminate\Database\QueryException $e) {
            Log::error('updateProfile | DB error', ['error' => $e->getMessage()]);
            return $this->errorResponse('Database error', $e->getMessage(), 500);

        } catch (\Exception $e) {
            Log::error('updateProfile | Unexpected error', ['error' => $e->getMessage()]);
            return $this->errorResponse('Unexpected error', $e->getMessage(), 500);
        }
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        if (!Hash::check($request->current_password, $request->user()->password)) {
            return $this->errorResponse('Current password is incorrect', null, 401);
        }

        $request->user()->update(['password' => $request->new_password]);

        return $this->successResponse(null, 'Password changed successfully', 200);
    }

    /* Helpers */
    private function successResponse($data, string $message, int $code = 200): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $message, 'data' => $data], $code);
    }

    private function errorResponse(string $message, $errors = null, int $code = 400): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message, 'errors' => $errors], $code);
    }

    private function buildProfilePayload(User $user): array
    {
        $favoritesCount = DB::table('favorites')->where('user_id', $user->id)->count();
        $downloadsCount = DB::table('offline_downloads')->where('user_id', $user->id)->count();
        $booksReadCount = DB::table('reading_sessions')->where('user_id', $user->id)->where('duration_seconds', '>', 0)->distinct('book_id')->count('book_id');
        $totalReadingSeconds = (int) DB::table('reading_activity_daily')->where('user_id', $user->id)->sum('seconds_read');
        $readingDaysCount = DB::table('reading_activity_daily')->where('user_id', $user->id)->where('seconds_read', '>', 0)->count();

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
