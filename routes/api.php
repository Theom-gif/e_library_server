<?php

use App\Http\Controllers\Admin\BookController as AdminBookController;
use App\Http\Controllers\Api\AdminCategoryController;
use App\Http\Controllers\Api\AdminDashboardController;
use App\Http\Controllers\Api\AdminSystemMonitorController;
use App\Http\Controllers\Api\AdminReaderLeaderboardController;
use App\Http\Controllers\Api\AdminSettingsController;
use App\Http\Controllers\Api\AdminUserController;
use App\Http\Controllers\Api\AuthorDashboardController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookController;
use App\Http\Controllers\Api\BookWorkflowController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\Reader\DownloadController as ReaderDownloadController;
use App\Http\Controllers\Api\Reader\FavoriteController as ReaderFavoriteController;
use App\Http\Controllers\Api\Reader\ReadingSessionController;
use App\Http\Controllers\Api\Reader\ReviewController as ReaderReviewController;
use App\Http\Controllers\Author\BookController as AuthorBookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::get('/health', function () {
    return response()->json([
        'hosting' => 'success',
        'status' => 'healthy',
        'timestamp' => now()->toIso8601String(),
    ]);
});

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/author_registration', [AuthController::class, 'authorRegister']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/book-view', [BookController::class, 'storeBookView']);
    Route::post('/request-password-reset', [AuthController::class, 'requestPasswordReset']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
});

Route::apiResource('categories', CategoryController::class);
Route::get('/category', [CategoryController::class, 'index']);
Route::get('/categories/all', [CategoryController::class, 'index']);
Route::get('/author/categories', [CategoryController::class, 'index']);
Route::get('/author/books/categories', [CategoryController::class, 'index']);

Route::get('/books', [BookWorkflowController::class, 'approvedBooks'])->name('api.books.index');
Route::get('/book', [BookWorkflowController::class, 'approvedBooks']);
Route::get('/books/discover', [BookWorkflowController::class, 'discoverBooks'])->name('api.books.discover');
Route::get('/books/{book}', [BookWorkflowController::class, 'show'])->name('api.books.show');
Route::get('/books/{book}/read', [BookWorkflowController::class, 'readPdf'])->name('api.books.read');
Route::get('/books/{book}/cover', [BookWorkflowController::class, 'viewCover'])->name('api.books.cover');
Route::post('/books/{book}/download', [BookWorkflowController::class, 'resolveDownload'])->name('api.books.download.resolve');

// Legacy write aliases used by existing frontend clients.
Route::post('/books', [BookController::class, 'store']);
Route::post('/book', [BookController::class, 'store']);
Route::patch('/books/{id}', [BookController::class, 'updateBook']);
Route::delete('/books/{id}', [BookController::class, 'deleteBook']);
Route::post('/author/upload', [BookController::class, 'store']);
Route::post('/author/books/upload', [BookController::class, 'store']);
Route::post('/author/books/create', [BookController::class, 'store']);

Route::get('/books/{book}/comments', [ReaderReviewController::class, 'listComments'])->name('api.books.comments.index');
Route::get('/books/{book}/reviews', [ReaderReviewController::class, 'listReviews'])->name('api.books.reviews.index');
Route::get('/books/{book}/ratings', [ReaderReviewController::class, 'ratings'])->name('api.books.ratings.index');
Route::get('/books/{book}/rating', [ReaderReviewController::class, 'ratings']);
Route::get('/ratings/{book}', [ReaderReviewController::class, 'ratings']);

Route::apiResource('posts', PostController::class);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'getCurrentUser']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::post('/books/{book}/comments', [ReaderReviewController::class, 'addComment'])->name('api.books.comments.store');
    Route::post('/books/{book}/reviews', [ReaderReviewController::class, 'createReview'])->name('api.books.reviews.store');
    Route::patch('/reviews/{id}', [ReaderReviewController::class, 'updateReview'])->name('api.reviews.update');
    Route::delete('/reviews/{id}', [ReaderReviewController::class, 'deleteReview'])->name('api.reviews.destroy');
    Route::post('/reviews/{id}/like', [ReaderReviewController::class, 'likeReview'])->name('api.reviews.like');
    Route::post('/reviews/{id}/unlike', [ReaderReviewController::class, 'unlikeReview'])->name('api.reviews.unlike');
    Route::post('/books/{book}/ratings', [ReaderReviewController::class, 'rate'])->name('api.books.ratings.store');
    Route::post('/books/{book}/rating', [ReaderReviewController::class, 'rate']);

    Route::get('/favorites', [ReaderFavoriteController::class, 'index']);
    Route::post('/favorites', [ReaderFavoriteController::class, 'store']);
    Route::delete('/favorites/{bookId}', [ReaderFavoriteController::class, 'destroy']);
    Route::get('/downloads', [ReaderDownloadController::class, 'index']);
    Route::post('/books/{book}/downloads', [ReaderDownloadController::class, 'store']);

    Route::prefix('reading-sessions')->group(function () {
        Route::post('/start', [ReadingSessionController::class, 'start']);
        Route::post('/{sessionId}/heartbeat', [ReadingSessionController::class, 'heartbeat']);
        Route::post('/{sessionId}/finish', [ReadingSessionController::class, 'finish']);
    });

    Route::prefix('me')->group(function () {
        Route::get('/', [AuthController::class, 'getCurrentUser']);
        Route::get('/profile', [AuthController::class, 'getCurrentUser']);
        Route::match(['patch', 'put'], '/profile', [AuthController::class, 'updateProfile']);
        Route::get('/reading-activity', [ReadingSessionController::class, 'activity']);
    });

    Route::prefix('auth')->group(function () {
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
});

Route::middleware(['auth:sanctum', 'role:author'])->prefix('author')->group(function () {
    Route::get('/dashboard', [AuthorDashboardController::class, 'index']);
    Route::get('/dashboard/stats', [AuthorDashboardController::class, 'stats']);
    Route::get('/dashboard/performance', [AuthorDashboardController::class, 'performance']);
    Route::get('/dashboard/top-books', [AuthorDashboardController::class, 'topBooks']);
    Route::get('/dashboard/feedback', [AuthorDashboardController::class, 'feedback']);
    Route::get('/dashboard/demographics', [AuthorDashboardController::class, 'demographics']);

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

Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index']);
    Route::get('/dashboard/activity', [AdminDashboardController::class, 'activity']);
    Route::get('/leaderboard/readers', [AdminReaderLeaderboardController::class, 'index']);
    Route::get('/monitor/summary', [AdminSystemMonitorController::class, 'summary']);
    Route::get('/monitor/activity', [AdminSystemMonitorController::class, 'activity']);
    Route::get('/monitor/health', [AdminSystemMonitorController::class, 'health']);
    Route::get('/monitor/top-books', [AdminSystemMonitorController::class, 'topBooks']);
    Route::get('/monitor/dashboard', [AdminSystemMonitorController::class, 'dashboard']);

    Route::get('/settings', [AdminSettingsController::class, 'show']);
    Route::match(['put', 'patch', 'post'], '/settings', [AdminSettingsController::class, 'changePassword']);
    Route::match(['put', 'patch', 'post'], '/settings/change-password', [AdminSettingsController::class, 'changePassword']);
    Route::match(['put', 'patch', 'post'], '/settings/password', [AdminSettingsController::class, 'changePassword']);

    Route::get('/categories', [AdminCategoryController::class, 'index']);
    Route::post('/categories', [AdminCategoryController::class, 'store']);

    Route::get('/users', [AdminUserController::class, 'index']);
    Route::get('/users/{user}', [AdminUserController::class, 'show']);
    Route::match(['put', 'patch', 'post'], '/users/{user}', [AdminUserController::class, 'update']);
    Route::delete('/users/{user}', [AdminUserController::class, 'destroy']);

    Route::get('/books', [AdminBookController::class, 'index']);
    Route::get('/books/approved', [AdminBookController::class, 'approved']);
    Route::get('/books/pending', [BookWorkflowController::class, 'pendingBooks']);
    Route::post('/books/{book}/approve', [AdminBookController::class, 'approve']);
    Route::post('/books/{book}/reject', [AdminBookController::class, 'reject']);
    Route::patch('/books/{book}/review', [BookWorkflowController::class, 'review']);
});
