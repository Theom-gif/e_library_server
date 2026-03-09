<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\admin\category\BookViewRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Requests\admin\RegistationAuthor;
use App\Models\Book;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    // --------------------------------------
    // Register User
    // --------------------------------------
    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            $user = User::create([
                'firstname' => $request->firstname,
                'lastname'  => $request->lastname,
                'email'     => $request->email,
                'password'  => $request->password, // auto-hashed by model
                'role_id'   => $request->role_id,
            ]);

            $token = $user->createToken('auth_token')->plainTextToken;

            return $this->successResponse([
                'user'  => $user,
                'token' => $token,
            ], 'User registered successfully', 201);

        } catch (\Exception $e) {
            return $this->errorResponse('Registration failed', $e->getMessage(), 500);
        }
    }

    // --------------------------------------
    // Register Author
    // --------------------------------------
    public function authorRegister(RegistationAuthor $request): JsonResponse
    {
        return $this->register($request);
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
        $request->user()->update($request->only([
            'firstname',
            'lastname',
            'bio',
            'facebook_url',
            'avatar',
        ]));

        return $this->successResponse(
            $request->user(),
            'Profile updated successfully',
            200
        );
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

<<<<<<< HEAD
    /*
    |--------------------------------------------------------------------------
    | Book APIs
    |--------------------------------------------------------------------------
    */

    public function listBooks(): JsonResponse
    {
        try {
            $books = Book::query()->latest('id')->get()->map(function (Book $book) {
                if ($book->cover_image_path && !$book->cover_image_url) {
                    $book->cover_image_url = url(Storage::disk('public')->url($book->cover_image_path));
                }
                if ($book->book_file_path && !$book->book_file_url) {
                    $book->book_file_url = url(Storage::disk('public')->url($book->book_file_path));
                }

                $item = $book->toArray();
                $item['publishedYear'] = $book->published_year;
                $item['coverImageUrl'] = $book->cover_image_url;
                $item['bookFileUrl'] = $book->book_file_url;
                $item['coverImagePath'] = $book->cover_image_path;
                $item['bookFilePath'] = $book->book_file_path;

                return $item;
            })->values();

            return response()->json([
                'success' => true,
                'message' => 'Books retrieved successfully',
                'data' => $books,
                'books' => $books,
                'results' => $books,
            ], 200);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve books', $e->getMessage(), 500);
        }
    }

    public function importBooks(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'books' => 'required|array|min:1',
                'books.*.title' => 'required|string|max:255',
                'books.*.author' => 'nullable|string|max:255',
                'books.*.description' => 'nullable|string',
                'books.*.category' => 'nullable|string|max:255',
                'books.*.published_year' => 'nullable|integer|min:1000|max:' . (now()->year + 1),
                'books.*.cover_image_url' => 'nullable|url|max:2048',
                'books.*.book_file_url' => 'nullable|url|max:2048',
            ]);

            $created = 0;
            foreach ($validated['books'] as $item) {
                $book = new Book();
                $book->title = $item['title'];
                $book->author = $item['author'] ?? null;
                $book->description = $item['description'] ?? null;
                $book->category = $item['category'] ?? null;
                $book->published_year = $item['published_year'] ?? null;
                $book->cover_image_url = $item['cover_image_url'] ?? null;
                $book->book_file_url = $item['book_file_url'] ?? null;
                $book->user_id = optional($request->user())->id;
                $book->save();
                $created++;
            }

            return $this->successResponse([
                'imported_count' => $created,
            ], 'Books imported successfully', 201);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to import books', $e->getMessage(), 500);
        }
    }

    public function updateBook(Request $request, int $id): JsonResponse
    {
        try {
            $book = Book::find($id);
            if (!$book) {
                return $this->errorResponse('Book not found', null, 404);
            }

            $validated = $request->validate([
                'title' => 'sometimes|required|string|max:255',
                'author' => 'sometimes|nullable|string|max:255',
                'description' => 'sometimes|nullable|string',
                'category' => 'sometimes|nullable|string|max:255',
                'published_year' => 'sometimes|nullable|integer|min:1000|max:' . (now()->year + 1),
                'cover_image' => 'sometimes|nullable|image|max:5120',
                'coverImage' => 'sometimes|nullable|image|max:5120',
                'book_file' => 'sometimes|nullable|file|mimes:pdf,epub,doc,docx|max:20480',
                'bookFile' => 'sometimes|nullable|file|mimes:pdf,epub,doc,docx|max:20480',
                'cover_image_url' => 'sometimes|nullable|url|max:2048',
                'coverImageUrl' => 'sometimes|nullable|string|max:2048',
                'book_file_url' => 'sometimes|nullable|url|max:2048',
                'bookFileUrl' => 'sometimes|nullable|string|max:2048',
            ]);
            $coverFile = $request->file('cover_image') ?? $request->file('coverImage');
            $bookFile = $request->file('book_file') ?? $request->file('bookFile');

            if ($coverFile) {
                if ($book->cover_image_path) {
                    Storage::disk('public')->delete($book->cover_image_path);
                }
                $validated['cover_image_path'] = $coverFile->store('books/covers', 'public');
            }

            if ($bookFile) {
                if ($book->book_file_path) {
                    Storage::disk('public')->delete($book->book_file_path);
                }
                $validated['book_file_path'] = $bookFile->store('books/files', 'public');
            }

            if (!empty($validated['cover_image_path']) && empty($validated['cover_image_url'])) {
                $validated['cover_image_url'] = url(Storage::disk('public')->url($validated['cover_image_path']));
            }

            if (!empty($validated['book_file_path']) && empty($validated['book_file_url'])) {
                $validated['book_file_url'] = url(Storage::disk('public')->url($validated['book_file_path']));
            }

            unset($validated['cover_image'], $validated['book_file'], $validated['coverImage'], $validated['bookFile'], $validated['coverImageUrl'], $validated['bookFileUrl']);

            $book->update($validated);
            $book->refresh();

            return $this->successResponse($book, 'Book updated successfully', 200);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update book', $e->getMessage(), 500);
        }
    }

    public function deleteBook(int $id): JsonResponse
    {
        try {
            $book = Book::find($id);
            if (!$book) {
                return $this->errorResponse('Book not found', null, 404);
            }

            if ($book->cover_image_path) {
                Storage::disk('public')->delete($book->cover_image_path);
            }
            if ($book->book_file_path) {
                Storage::disk('public')->delete($book->book_file_path);
            }

            $book->delete();

            return $this->successResponse(null, 'Book deleted successfully', 200);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to delete book', $e->getMessage(), 500);
        }
    }

    public function storeBookView(BookViewRequest $request): JsonResponse
    {
        try {
            $now = now();
            $bookViewId = DB::table('book_views')->insertGetId([
                'book_id' => $request->book_id,
                'user_id' => $request->user_id ?? optional($request->user())->id,
                'ip_address' => $request->ip_address ?? $request->ip(),
                'user_agent' => $request->user_agent ?? $request->userAgent(),
                'viewed_at' => $request->viewed_at ?? $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $bookView = DB::table('book_views')->where('id', $bookViewId)->first();

            return $this->successResponse($bookView, 'Book view saved successfully', 201);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to save book view', $e->getMessage(), 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Helper Methods
    |--------------------------------------------------------------------------
    */
=======
    // --------------------------------------
    // Helper Methods
    // --------------------------------------
>>>>>>> cfcb6af5bd5dc42baafef2d32df9a8686b18bc98
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
}
