<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
Route::apiResource('categories', CategoryController::class);
use App\Http\Controllers\Api\PostController;

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
        'status' => 'healthy',
        'timestamp' => now()->toIso8601String(),
    ]);
});


// Public Authentication Routes
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/author_registration', [AuthController::class, 'authorRegister']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/book', [AuthController::class, 'storeBook']);
    Route::get('/books', [AuthController::class, 'listBooks']);
    Route::post('/books/import-local', [AuthController::class, 'importBooks']);
    Route::patch('/books/{id}', [AuthController::class, 'updateBook']);
    Route::delete('/books/{id}', [AuthController::class, 'deleteBook']);
    Route::post('/book-view', [AuthController::class, 'storeBookView']);
    Route::post('/request-password-reset', [AuthController::class, 'requestPasswordReset']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
});

// Compatibility Book Routes (no /auth prefix) for frontend clients
Route::get('/books', [AuthController::class, 'listBooks']);
Route::post('/books', [AuthController::class, 'storeBook']);
Route::patch('/books/{id}', [AuthController::class, 'updateBook']);
Route::delete('/books/{id}', [AuthController::class, 'deleteBook']);
Route::get('/book', [AuthController::class, 'listBooks']);
Route::post('/book', [AuthController::class, 'storeBook']);

// Protected Authentication Routes (require auth token)
Route::middleware('auth:sanctum')->prefix('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'getCurrentUser']);
    Route::patch('/update-profile', [AuthController::class, 'updateProfile']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);
});

// Protected User Routes
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Example API routes for REST API
Route::apiResource('posts', PostController::class);
