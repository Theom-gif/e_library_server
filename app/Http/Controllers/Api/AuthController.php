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
use App\Models\Category;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

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

    /*
    |--------------------------------------------------------------------------
    | Book APIs
    |--------------------------------------------------------------------------
    */

    public function listBooks(Request $request): JsonResponse
    {
        try {
            $includeDeleted = in_array(
                strtolower((string) $request->query('include_deleted', '0')),
                ['1', 'true', 'yes'],
                true
            );

            $query = Book::query()->latest('id');
            if ($includeDeleted) {
                $query->withTrashed();
            }

            $books = $query->get()->map(function (Book $book) {
                $item = $book->toApiArray();
                $item['is_deleted'] = $book->trashed();

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
                'category_id' => 'sometimes|nullable|integer|exists:categories,id',
                'author_id' => 'sometimes|nullable|integer|exists:users,id',
                'approved_by' => 'sometimes|nullable|integer|exists:users,id',
                'title' => 'sometimes|required|string|max:255',
                'slug' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('books', 'slug')->ignore($book->id)],
                'author' => 'sometimes|nullable|string|max:255',
                'author_name' => 'sometimes|nullable|string|max:255',
                'description' => 'sometimes|nullable|string',
                'category' => 'sometimes|nullable|string|max:255',
                'published_year' => 'sometimes|nullable|integer|min:1000|max:' . (now()->year + 1),
                'pdf_path' => 'sometimes|nullable|string|max:2048',
                'book_file_path' => 'sometimes|nullable|string|max:2048',
                'cover_image_path' => 'sometimes|nullable|string|max:2048',
                'cover_image' => 'sometimes|nullable|image|max:5120',
                'coverImage' => 'sometimes|nullable|image|max:5120',
                'book_file' => 'sometimes|nullable|file|mimes:pdf,epub,doc,docx|max:20480',
                'bookFile' => 'sometimes|nullable|file|mimes:pdf,epub,doc,docx|max:20480',
                'cover_image_url' => 'sometimes|nullable|string|max:2048',
                'coverImageUrl' => 'sometimes|nullable|string|max:2048',
                'book_file_url' => 'sometimes|nullable|string|max:2048',
                'bookFileUrl' => 'sometimes|nullable|string|max:2048',
                'status' => ['sometimes', 'nullable', Rule::in(['pending', 'approved', 'rejected'])],
                'approved_at' => 'sometimes|nullable|date',
                'rejection_reason' => 'sometimes|nullable|string',
                'published_at' => 'sometimes|nullable|date',
                'language' => 'sometimes|nullable|string|max:12',
                'total_pages' => 'sometimes|nullable|integer|min:1',
                'file_size_bytes' => 'sometimes|nullable|integer|min:0',
            ]);

            $coverFile = $request->file('cover_image') ?? $request->file('coverImage');
            $bookFile = $request->file('book_file') ?? $request->file('bookFile');
            $bookFileUrl = $validated['book_file_url'] ?? $validated['bookFileUrl'] ?? null;
            $coverImageUrl = $validated['cover_image_url'] ?? $validated['coverImageUrl'] ?? null;

            if ($coverFile) {
                if ($book->cover_image_path) {
                    Storage::disk('public')->delete($book->cover_image_path);
                }
                $validated['cover_image_path'] = $coverFile->store('books/covers', 'public');
            }

            if ($bookFile) {
                $oldPdfPath = $book->pdf_path ?: $book->book_file_path;
                if ($oldPdfPath && !preg_match('/^(https?:|data:)/i', (string) $oldPdfPath)) {
                    Storage::disk('public')->delete($oldPdfPath);
                }
                $validated['pdf_path'] = $bookFile->store('books/pdfs', 'public');
                $validated['book_file_path'] = $validated['pdf_path'];
                $validated['original_pdf_name'] = $bookFile->getClientOriginalName();
                $validated['pdf_mime_type'] = $bookFile->getClientMimeType();
                $validated['file_size_bytes'] = $bookFile->getSize();
            }

            if (!empty($validated['cover_image_path']) && empty($validated['cover_image_url'])) {
                $validated['cover_image_url'] = url(Storage::disk('public')->url($validated['cover_image_path']));
            }

            if (!empty($validated['pdf_path']) && empty($validated['book_file_url'])) {
                $validated['book_file_url'] = url(Storage::disk('public')->url($validated['pdf_path']));
            }

            if (!$coverFile && is_string($coverImageUrl) && $coverImageUrl !== '') {
                $validated['cover_image_url'] = $coverImageUrl;
            }

            if (!$bookFile && is_string($bookFileUrl) && $bookFileUrl !== '') {
                $validated['book_file_url'] = $bookFileUrl;
                $validated['pdf_path'] = $bookFileUrl;
                $validated['book_file_path'] = $bookFileUrl;
            }

            if (array_key_exists('author', $validated)) {
                $validated['author_name'] = $validated['author'];
            }
            if (array_key_exists('author_name', $validated) && !array_key_exists('author', $validated)) {
                $validated['author'] = $validated['author_name'];
            }

            if (array_key_exists('category', $validated)) {
                $categoryName = trim((string) $validated['category']);
                if ($categoryName !== '') {
                    $existingCategory = Category::query()
                        ->whereRaw('LOWER(TRIM(name)) = ?', [Str::lower($categoryName)])
                        ->first();

                    if (!$existingCategory) {
                        $baseSlug = Str::slug($categoryName) ?: 'category';
                        $slug = $baseSlug;
                        $counter = 2;
                        while (Category::query()->where('slug', $slug)->exists()) {
                            $slug = $baseSlug.'-'.$counter;
                            $counter++;
                        }

                        $existingCategory = Category::create([
                            'name' => $categoryName,
                            'slug' => $slug,
                            'is_active' => true,
                        ]);
                    }

                    $validated['category_id'] = $existingCategory->id;
                    $validated['category'] = $existingCategory->name;
                }
            }
            if (array_key_exists('category_id', $validated) && $validated['category_id']) {
                $existingCategory = Category::query()->find($validated['category_id']);
                if ($existingCategory) {
                    $validated['category'] = $existingCategory->name;
                }
            }

            unset(
                $validated['cover_image'],
                $validated['book_file'],
                $validated['coverImage'],
                $validated['bookFile'],
                $validated['coverImageUrl'],
                $validated['bookFileUrl']
            );

            $book->update(Book::compatibleAttributes($validated));
            $book->refresh();

            return $this->successResponse($book->toApiArray(), 'Book updated successfully', 200);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update book', $e->getMessage(), 500);
        }
    }

    public function deleteBook(int $id): JsonResponse
    {
        try {
            $book = Book::withTrashed()->find($id);
            if (!$book) {
                return $this->errorResponse('Book not found', null, 404);
            }

            if ($book->cover_image_path) {
                Storage::disk('public')->delete($book->cover_image_path);
            }

            $bookAssetPaths = array_filter([
                $book->pdf_path,
                $book->book_file_path,
            ]);
            foreach (array_unique($bookAssetPaths) as $path) {
                if (!preg_match('/^(https?:|data:)/i', (string) $path)) {
                    Storage::disk('public')->delete($path);
                }
            }

            // Permanently remove the record so it does not remain as a soft-deleted row.
            $book->forceDelete();

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
}
