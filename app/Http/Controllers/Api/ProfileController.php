<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Requests\ChangePasswordRequest;
use App\Models\User;
use App\Models\UserAvatar;
use App\Support\PublicImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

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
            Log::info('updateProfile | avatar request', [
                'has_avatar' => $request->hasFile('avatar'),
                'has_avatar_file' => $request->hasFile('avatar_file'),
                'has_photo' => $request->hasFile('photo'),
                'method' => $request->method(),
            ]);

            $avatarFile = $this->extractAvatarFile($request);

            if ($avatarFile) {
                $upload = $this->storeValidatedAvatar($request->user(), $avatarFile);

                if (!$upload['success']) {
                    return $this->validationErrorResponse($upload['errors']);
                }

                $payload['avatar'] = $this->buildAvatarUrl($request->user(), $upload['updated_at']);
                Log::info('updateProfile | stored avatar in db', ['user_id' => $request->user()->id]);
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

    private function validationErrorResponse(array $errors): JsonResponse
    {
        return $this->errorResponse('Validation error', $errors, 422);
    }

    private function buildProfilePayload(User $user): array
    {
        $user->loadMissing('avatarImage');

        $favoritesCount = DB::table('favorites')->where('user_id', $user->id)->count();
        $downloadsCount = DB::table('offline_downloads')->where('user_id', $user->id)->count();
        $booksReadCount = DB::table('reading_sessions')->where('user_id', $user->id)->where('duration_seconds', '>', 0)->distinct('book_id')->count('book_id');
        $totalReadingSeconds = (int) DB::table('reading_activity_daily')->where('user_id', $user->id)->sum('seconds_read');
        $readingDaysCount = DB::table('reading_activity_daily')->where('user_id', $user->id)->where('seconds_read', '>', 0)->count();

        $photoUrl = $this->buildAvatarUrl($user, $user->avatarImage?->updated_at);

        $userData = [
            'id' => $user->id,
            'role_id' => $user->role_id,
            'firstname' => $user->firstname,
            'lastname' => $user->lastname,
            'first_name' => $user->firstname,
            'last_name' => $user->lastname,
            'name' => trim(($user->firstname ?? '').' '.($user->lastname ?? '')),
            'full_name' => trim(($user->firstname ?? '').' '.($user->lastname ?? '')),
            'email' => $user->email,
            'bio' => $user->bio,
            'facebook_url' => $user->facebook_url,
            'avatar' => $user->avatar,
            'avatar_url' => $photoUrl,
            'photo' => $photoUrl,
            'photo_url' => $photoUrl,
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

        return PublicImage::normalize($value, 'avatars')['url'] ?? $value;
    }

    private function extractAvatarFile(Request $request): ?UploadedFile
    {
        $file = $request->file('avatar_file');

        if (!$file instanceof UploadedFile) {
            $file = $request->file('avatar');
        }

        if (!$file instanceof UploadedFile) {
            $file = $request->file('photo');
        }

        return $file instanceof UploadedFile ? $file : null;
    }

    private function storeValidatedAvatar(User $user, UploadedFile $file): array
    {
        $validator = Validator::make(['photo' => $file], [
            'photo' => 'required|mimetypes:image/jpeg,image/png|max:5120',
        ], [
            'photo.required' => 'Only JPG/PNG up to 5MB',
            'photo.mimetypes' => 'Only JPG/PNG up to 5MB',
            'photo.max' => 'Only JPG/PNG up to 5MB',
        ]);

        if ($validator->fails()) {
            Log::warning('storeValidatedAvatar | validation failed', [
                'errors' => $validator->errors()->toArray(),
            ]);

            return [
                'success' => false,
                'errors' => [
                    'photo' => $validator->errors()->get('photo'),
                ],
            ];
        }

        $avatar = $user->avatarImage()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                'bytes' => file_get_contents($file->getRealPath()),
                'hash' => hash_file('sha256', $file->getRealPath()),
            ]
        );

        return [
            'success' => true,
            'updated_at' => $avatar->updated_at,
        ];
    }

    private function buildAvatarPayload(User $user): array
    {
        $profile = $this->buildProfilePayload($user);

        return [
            'photo' => $profile['user']['photo'],
            'photo_url' => $profile['user']['photo_url'],
            'avatar' => $profile['user']['avatar'],
            'avatar_url' => $profile['user']['avatar_url'],
            'user' => $profile['user'],
            'stats' => $profile['stats'],
        ];
    }

    private function buildAvatarUrl(User $user, $updatedAt = null): ?string
    {
        if (!$updatedAt && !$user->relationLoaded('avatarImage')) {
            $user->loadMissing('avatarImage');
            $updatedAt = $user->avatarImage?->updated_at;
        }

        if (!$updatedAt) {
            return $this->resolveAvatarUrl($user->avatar);
        }

        $version = optional($updatedAt)->timestamp;

        return route('avatars.show', ['userId' => $user->id, 'v' => $version], false);
    }

    public function uploadAvatar(Request $request): JsonResponse
    {
        try {
            $file = $this->extractAvatarFile($request);

            if (!$file) {
                return $this->errorResponse('No avatar file provided', null, 422);
            }

            $upload = $this->storeValidatedAvatar($request->user(), $file);

            if (!$upload['success']) {
                return $this->validationErrorResponse($upload['errors']);
            }

            $user = $request->user();
            $user->avatar = $this->buildAvatarUrl($user, $upload['updated_at']);
            $user->save();

            return $this->successResponse($this->buildAvatarPayload($user->fresh()), 'Avatar uploaded successfully', 200);

        } catch (\Exception $e) {
            Log::error('uploadAvatar | Unexpected error', ['error' => $e->getMessage()]);
            return $this->errorResponse('Unexpected error', $e->getMessage(), 500);
        }
    }

    public function showAvatar(Request $request, int $userId): Response
    {
        $avatar = UserAvatar::query()
            ->where('user_id', $userId)
            ->first();

        if (!$avatar) {
            abort(404);
        }

        $bytes = $avatar->bytes;
        $length = strlen($bytes);
        $etag = '"'.$avatar->hash.'"';

        if ($request->header('If-None-Match') === $etag) {
            return response('', 304, [
                'Cache-Control' => 'public, max-age=86400',
                'ETag' => $etag,
            ]);
        }

        return response($bytes, 200, [
            'Content-Type' => $avatar->mime_type,
            'Content-Length' => (string) $length,
            'Cache-Control' => 'public, max-age=86400',
            'ETag' => $etag,
        ]);
    }

}
