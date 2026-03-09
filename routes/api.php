<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AdminSettingsController;
use App\Http\Controllers\Api\AdminUserController;
use App\Http\Controllers\Api\BookWorkflowController;
use App\Http\Controllers\Api\PostController;
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
    Route::post('/request-password-reset', [AuthController::class, 'requestPasswordReset']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
});

// Protected Authentication Routes (require auth token)
Route::middleware('auth:sanctum')->prefix('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'getCurrentUser']);
    Route::patch('/update-profile', [AuthController::class, 'updateProfile']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);
});

// Protected API routes (require auth)
Route::middleware('auth:sanctum')->group(function () {
    // Example: Posts API
    Route::apiResource('posts', PostController::class);
});
<<<<<<< HEAD

// Public approved books (read + cover preview)
Route::get('/books', [BookWorkflowController::class, 'approvedBooks'])->name('api.books.index');
Route::get('/books/discover', [BookWorkflowController::class, 'discoverBooks'])->name('api.books.discover');
Route::get('/books/{book}', [BookWorkflowController::class, 'show'])->name('api.books.show');
Route::get('/books/{book}/read', [BookWorkflowController::class, 'readPdf'])->name('api.books.read');
Route::get('/books/{book}/cover', [BookWorkflowController::class, 'viewCover'])->name('api.books.cover');

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

// Admin Book Moderation Routes
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {
    Route::get('/settings', [AdminSettingsController::class, 'show']);
    Route::match(['put', 'patch', 'post'], '/settings', [AdminSettingsController::class, 'changePassword']);
    Route::match(['put', 'patch', 'post'], '/settings/change-password', [AdminSettingsController::class, 'changePassword']);
    Route::match(['put', 'patch', 'post'], '/settings/password', [AdminSettingsController::class, 'changePassword']);
    Route::get('/users', [AdminUserController::class, 'index']);
    Route::get('/users/{user}', [AdminUserController::class, 'show']);
    Route::match(['put', 'patch', 'post'], '/users/{user}', [AdminUserController::class, 'update']);
    Route::delete('/users/{user}', [AdminUserController::class, 'destroy']);
    Route::get('/books/pending', [BookWorkflowController::class, 'pendingBooks']);
    Route::patch('/books/{book}/review', [BookWorkflowController::class, 'review']);
});
=======
>>>>>>> 6b204d3f9d2dd57488d4be054420c060758c94ed
