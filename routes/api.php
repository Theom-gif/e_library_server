<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Admin\BookController as AdminBookController;
use App\Http\Controllers\Api\AdminSettingsController;
use App\Http\Controllers\Api\AdminDashboardController;
use App\Http\Controllers\Api\AdminUserController;
use App\Http\Controllers\Api\BookController;
use App\Http\Controllers\Api\BookInteractionController;
use App\Http\Controllers\Api\BookWorkflowController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\Reader\FavoriteController as ReaderFavoriteController;
use App\Http\Controllers\Author\BookController as AuthorBookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and assigned to the "api" middleware group.
|
*/

// Health check endpoint
Route::get('/health', function () {
    return response()->json([
        'hosting' => 'success',
        'status' => 'healthy',
        'timestamp' => now()->toIso8601String(),
    ]);
});


// Public Authentication Routes
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/author_registration', [AuthController::class, 'authorRegister']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/book-view', [AuthController::class, 'storeBookView']);
    Route::post('/request-password-reset', [AuthController::class, 'requestPasswordReset']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
});
Route::apiResource('categories', CategoryController::class);
Route::get('/category', [CategoryController::class, 'index']);
Route::get('/categories/all', [CategoryController::class, 'index']);

// Compatibility Book Routes (no /auth prefix) for frontend clients
Route::get('/books', [AuthController::class, 'listBooks']);
Route::post('/books', [BookController::class, 'store']);
Route::patch('/books/{id}', [AuthController::class, 'updateBook']);
Route::delete('/books/{id}', [AuthController::class, 'deleteBook']);
Route::get('/book', [AuthController::class, 'listBooks']);
Route::post('/book', [BookController::class, 'store']);
Route::post('/author/upload', [BookController::class, 'store']);
Route::post('/author/books/upload', [BookController::class, 'store']);
Route::post('/author/books/create', [BookController::class, 'store']);
Route::get('/author/categories', [CategoryController::class, 'index']);
Route::get('/author/books/categories', [CategoryController::class, 'index']);

// Protected Authentication Routes (require auth token)
Route::middleware('auth:sanctum')->prefix('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'getCurrentUser']);
    Route::patch('/update-profile', [AuthController::class, 'updateProfile']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);
    Route::middleware('role:author,admin')->group(function () {
        Route::get('/books', [AuthorBookController::class, 'index']);
        Route::get('/books/{book}', [AuthorBookController::class, 'show']);
        Route::post('/book', [AuthorBookController::class, 'store']);
        Route::match(['patch', 'post'], '/books/{book}', [AuthorBookController::class, 'update']);
        Route::delete('/books/{book}', [AuthorBookController::class, 'destroy']);
    });
});

// Protected User Routes
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Example API routes for REST API
Route::apiResource('posts', PostController::class);

// Protected API routes (require auth)
Route::middleware('auth:sanctum')->group(function () {
    // Example: Posts API
    Route::apiResource('posts', PostController::class);
});
// Public approved books (read + cover preview)
Route::get('/books', [BookWorkflowController::class, 'approvedBooks'])->name('api.books.index');
Route::get('/books/discover', [BookWorkflowController::class, 'discoverBooks'])->name('api.books.discover');
Route::get('/books/{book}', [BookWorkflowController::class, 'show'])->name('api.books.show');
Route::get('/books/{book}/read', [BookWorkflowController::class, 'readPdf'])->name('api.books.read');
Route::get('/books/{book}/cover', [BookWorkflowController::class, 'viewCover'])->name('api.books.cover');
Route::get('/books/{book}/comments', [BookInteractionController::class, 'listComments'])->name('api.books.comments.index');
Route::get('/books/{book}/ratings', [BookInteractionController::class, 'ratings'])->name('api.books.ratings.index');
Route::get('/books/{book}/rating', [BookInteractionController::class, 'ratings']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/books/{book}/comments', [BookInteractionController::class, 'addComment'])->name('api.books.comments.store');
    Route::post('/books/{book}/ratings', [BookInteractionController::class, 'rate'])->name('api.books.ratings.store');
    Route::post('/books/{book}/rating', [BookInteractionController::class, 'rate']);
    Route::post('/rating', [BookInteractionController::class, 'rate']);
});

// Legacy compatibility endpoints
Route::get('/ratings/{book}', [BookInteractionController::class, 'ratings']);

// Author Book Submission Routes
Route::middleware(['auth:sanctum', 'role:author'])->prefix('author')->group(function () {
    Route::get('/research', [BookWorkflowController::class, 'authorResearch']);
    Route::get('/search', [BookWorkflowController::class, 'authorResearch']);
    Route::post('/books/upload', [BookWorkflowController::class, 'upload']);
    Route::post('/books', [BookWorkflowController::class, 'upload']);
    Route::get('/books', [BookWorkflowController::class, 'myBooks']);
    Route::get('/books/search', [BookWorkflowController::class, 'myBooks']);
    Route::get('/books/research', [BookWorkflowController::class, 'authorResearch']);
    Route::get('/books/{book}', [BookWorkflowController::class, 'show']);
    Route::get('/books/{book}/read', [BookWorkflowController::class, 'readPdf']);
    Route::get('/books/{book}/cover', [BookWorkflowController::class, 'viewCover']);
});

// Favorites API (reader scoped)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/favorites', [ReaderFavoriteController::class, 'index']);
    Route::post('/favorites', [ReaderFavoriteController::class, 'store']);
    Route::delete('/favorites/{bookId}', [ReaderFavoriteController::class, 'destroy']);
});

// Admin Book Moderation Routes
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index']);
    Route::get('/dashboard/activity', [AdminDashboardController::class, 'activity']);
    Route::get('/settings', [AdminSettingsController::class, 'show']);
    Route::match(['put', 'patch', 'post'], '/settings', [AdminSettingsController::class, 'changePassword']);
    Route::match(['put', 'patch', 'post'], '/settings/change-password', [AdminSettingsController::class, 'changePassword']);
    Route::match(['put', 'patch', 'post'], '/settings/password', [AdminSettingsController::class, 'changePassword']);
    Route::get('/users', [AdminUserController::class, 'index']);
    Route::get('/users/{user}', [AdminUserController::class, 'show']);
    Route::match(['put', 'patch', 'post'], '/users/{user}', [AdminUserController::class, 'update']);
    Route::delete('/users/{user}', [AdminUserController::class, 'destroy']);
    Route::get('/books', [AdminBookController::class, 'index']);
    Route::get('/books/approved', [AdminBookController::class, 'approved']);
    Route::post('/books/{book}/approve', [AdminBookController::class, 'approve']);
    Route::post('/books/{book}/reject', [AdminBookController::class, 'reject']);
    Route::get('/books/pending', [BookWorkflowController::class, 'pendingBooks']);
    Route::patch('/books/{book}/review', [BookWorkflowController::class, 'review']);
});
