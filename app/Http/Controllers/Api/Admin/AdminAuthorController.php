<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\admin\ApproveRejectAuthorRequest;
use App\Mail\AuthorStatusMail;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AdminAuthorController extends Controller
{
    private const AUTHOR_ROLE_ID = 2;

    public function index(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'search' => 'nullable|string|max:255',
                'status' => ['nullable', Rule::in(['active', 'pending'])],
                'page' => 'nullable|integer|min:1',
                'per_page' => 'nullable|integer|min:1|max:100',
            ]);

            $perPage = (int) ($validated['per_page'] ?? 15);
            $search = trim((string) ($validated['search'] ?? ''));

            $query = User::query()->where('role_id', self::AUTHOR_ROLE_ID);

            if ($search !== '') {
                $query->where(function ($builder) use ($search) {
                    $builder->whereRaw("CONCAT(COALESCE(firstname, ''), ' ', COALESCE(lastname, '')) LIKE ?", ['%' . $search . '%'])
                        ->orWhere('firstname', 'like', '%' . $search . '%')
                        ->orWhere('lastname', 'like', '%' . $search . '%')
                        ->orWhere('email', 'like', '%' . $search . '%');
                });
            }

            $status = $validated['status'] ?? null;
            if ($status === 'active') {
                $query->where('is_active', true);
            } elseif ($status === 'pending') {
                $query->where('is_active', false);
            }

            $authors = $query->latest('created_at')->paginate($perPage);
            $authors->getCollection()->transform(fn(User $author) => $this->formatAuthorResponse($author));

            return response()->json([
                'success' => true,
                'data' => $authors,
                'message' => 'Authors retrieved successfully',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('AdminAuthorController@index error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve authors',
            ], 500);
        }
    }

    public function approveAuthor(ApproveRejectAuthorRequest $request, User $user): JsonResponse
    {
        $user->update([
            'is_active'    => true,
            'status'       => 'active',
            'approved_by'  => $request->user()->id,
            'approved_at'  => now(),
        ]);

        // 📩 Send approval email
        Mail::to($user->email)->send(
            new AuthorStatusMail(
                $user,
                'Approved',
                'Congratulations! Your author account has been approved.',
                'https://e-library-portal.app/login',
                'Click Here To Go In As Author'
            )
        );

        return response()->json([
            'message' => 'Author approved successfully',
            'data' => $this->formatAuthorResponse($user),
        ], 200);
    }
    public function rejectAuthor(ApproveRejectAuthorRequest $request, User $user): JsonResponse
    {
        $author = clone $user;

        // 📩 Send rejection email
        Mail::to($user->email)->send(
            new AuthorStatusMail(
                $user,
                'Rejected',
                'Sorry, your author application was rejected. Please contact support.',
                'https://e-library-portal.app/login',
                'Go To Author Portal'
            )
        );

        $this->deleteStoredAvatar($user->avatar);
        $user->tokens()->delete();
        $user->delete();

        return response()->json([
            'message' => 'Author rejected successfully',
            'data' => $this->formatAuthorResponse($author),
        ], 200);
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|min:2|max:255',
                'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
                'password' => [
                    'nullable',
                    'confirmed',
                    Password::min(8)
                        ->mixedCase()
                        ->numbers()
                        ->symbols(),
                ],
                'bio' => 'nullable|string|max:500',
                'profile_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120',
            ], [
                'password.required' => 'Password is required.',
                'password.confirmed' => 'Passwords do not match.',
            ]);

            $profileImagePath = $this->storeProfileImage($request, $validated['name']);
            [$firstName, $lastName] = $this->splitName($validated['name']);
            $password = $validated['password'] ?? Str::password(16);

            $author = User::query()->create([
                'role_id' => self::AUTHOR_ROLE_ID,
                'firstname' => $firstName,
                'lastname' => $lastName,
                'email' => $validated['email'],
                'password' => Hash::make($password),
                'bio' => $validated['bio'] ?? null,
                'avatar' => $profileImagePath,
                'is_active' => false,
                'status' => 'in_review',
                'invitation_token' => $this->generateInvitationToken(),
                'invitation_sent_at' => now(),
                'invitation_accepted_at' => null,
            ]);

            $this->sendInvitationEmail($author);

            return response()->json([
                'success' => true,
                'data' => $this->formatAuthorResponse($author),
                'message' => 'Author created successfully. Invitation email sent.',
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('AdminAuthorController@store error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to create author',
            ], 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        try {
            $author = $this->findAuthor($id);
            if (!$author) {
                return $this->notFoundResponse();
            }

            return response()->json([
                'success' => true,
                'data' => $this->formatAuthorResponse($author),
            ]);
        } catch (\Throwable $e) {
            Log::error('AdminAuthorController@show error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve author',
            ], 500);
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $author = $this->findAuthor($id);
            if (!$author) {
                return $this->notFoundResponse();
            }

            $validated = $request->validate([
                'name' => 'sometimes|string|min:2|max:255',
                'email' => [
                    'sometimes',
                    'email',
                    'max:255',
                    Rule::unique('users', 'email')->ignore($author->id),
                ],
                'bio' => 'nullable|string|max:500',
                'profile_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120',
            ]);

            $updates = [];

            if (array_key_exists('name', $validated)) {
                [$firstName, $lastName] = $this->splitName($validated['name']);
                $updates['firstname'] = $firstName;
                $updates['lastname'] = $lastName;
            }

            if (array_key_exists('email', $validated)) {
                $updates['email'] = $validated['email'];
            }

            if (array_key_exists('bio', $validated)) {
                $updates['bio'] = $validated['bio'];
            }

            if ($request->hasFile('profile_image')) {
                $this->deleteStoredAvatar($author->avatar);
                $updates['avatar'] = $this->storeProfileImage(
                    $request,
                    $validated['name'] ?? $this->displayName($author)
                );
            }

            if ($updates !== []) {
                $author->update($updates);
                $author->refresh();
            }

            return response()->json([
                'success' => true,
                'data' => $this->formatAuthorResponse($author),
                'message' => 'Author updated successfully',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('AdminAuthorController@update error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update author',
            ], 500);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $author = $this->findAuthor($id);
            if (!$author) {
                return $this->notFoundResponse();
            }

            $this->deleteStoredAvatar($author->avatar);
            $author->delete();

            return response()->json([
                'success' => true,
                'message' => 'Author deleted successfully',
            ]);
        } catch (\Throwable $e) {
            Log::error('AdminAuthorController@destroy error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete author',
            ], 500);
        }
    }

    public function resendInvitation(int $id): JsonResponse
    {
        try {
            $author = $this->findAuthor($id);
            if (!$author) {
                return $this->notFoundResponse();
            }

            $author->update([
                'invitation_token' => $this->generateInvitationToken(),
                'invitation_sent_at' => now(),
            ]);

            $this->sendInvitationEmail($author->refresh());

            return response()->json([
                'success' => true,
                'message' => 'Invitation email sent successfully',
            ]);
        } catch (\Throwable $e) {
            Log::error('AdminAuthorController@resendInvitation error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to send invitation email',
            ], 500);
        }
    }

    private function findAuthor(int $id): ?User
    {
        return User::query()
            ->where('id', $id)
            ->where('role_id', self::AUTHOR_ROLE_ID)
            ->first();
    }

    private function generateInvitationToken(): string
    {
        return hash('sha256', Str::random(100) . microtime(true));
    }

    private function sendInvitationEmail(User $author): void
    {
        $baseUrl = rtrim((string) env('FRONTEND_URL', env('APP_URL', url('/'))), '/');
        $invitationUrl = $baseUrl . '/author/setup?token=' . $author->invitation_token;

        Log::info('Author invitation email sent', [
            'author_id' => $author->id,
            'email' => $author->email,
            'invitation_url' => $invitationUrl,
        ]);
    }

    private function storeProfileImage(Request $request, string $name): ?string
    {
        if (!$request->hasFile('profile_image')) {
            return null;
        }

        $file = $request->file('profile_image');
        $filename = time() . '_' . Str::slug($name) . '.' . $file->getClientOriginalExtension();

        return $file->storeAs('authors', $filename, 'public');
    }

    private function deleteStoredAvatar(?string $path): void
    {
        $path = trim((string) $path);
        if ($path === '' || preg_match('/^(https?:|data:)/i', $path)) {
            return;
        }

        Storage::disk('public')->delete($path);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitName(string $name): array
    {
        $name = trim(preg_replace('/\s+/', ' ', $name) ?? '');
        if ($name === '') {
            return ['Author', ''];
        }

        $parts = explode(' ', $name, 2);

        return [$parts[0], $parts[1] ?? ''];
    }

    private function displayName(User $author): string
    {
        return trim(($author->firstname ?? '') . ' ' . ($author->lastname ?? '')) ?: 'Unknown';
    }

    /**
     * @return array<string, mixed>
     */
    private function formatAuthorResponse(User $author): array
    {
        return [
            'id' => $author->id,
            'name' => $this->displayName($author),
            'email' => $author->email,
            'bio' => $author->bio,
            'profile_image' => $author->avatar,
            'profile_image_url' => $this->profileImageUrl($author->avatar),
            'is_active' => (bool) ($author->is_active ?? false),
            'invitation_sent_at' => $author->invitation_sent_at,
            'invitation_accepted_at' => $author->invitation_accepted_at,
            'created_at' => $author->created_at,
            'updated_at' => $author->updated_at,
        ];
    }

    private function profileImageUrl(?string $path): ?string
    {
        $path = trim((string) $path);
        if ($path === '') {
            return null;
        }

        if (preg_match('/^(https?:|data:)/i', $path)) {
            return $path;
        }

        $storageUrl = Storage::disk('public')->url($path);

        if (preg_match('/^(https?:)\/\//i', $storageUrl)) {
            return $storageUrl;
        }

        return url($storageUrl);
    }

    private function notFoundResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Not found',
        ], 404);
    }
}
