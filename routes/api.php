<?php

use App\Http\Controllers\Api\Admin\AdminCategoryController;
use App\Http\Controllers\Api\Admin\AdminDashboardController;
use App\Http\Controllers\Api\Admin\AdminReaderLeaderboardController;
use App\Http\Controllers\Api\Admin\AdminSettingsController;
use App\Http\Controllers\Api\Admin\AdminSystemMonitorController;
use App\Http\Controllers\Api\Admin\AdminUserController;
use App\Http\Controllers\Api\Admin\BookController as AdminBookController;
use App\Http\Controllers\Api\Author\AuthorDashboardController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AchievementController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\BookController;
use App\Http\Controllers\Api\AuthorController;
use App\Http\Controllers\Api\BookWorkflowController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\Reader\DownloadController as ReaderDownloadController;
use App\Http\Controllers\Api\Reader\FavoriteController as ReaderFavoriteController;
use App\Http\Controllers\Api\Reader\ReadingSessionController;
use App\Http\Controllers\Api\Reader\ReviewController as ReaderReviewController;
use App\Http\Controllers\Api\Author\BookController as AuthorBookController;
use App\Http\Controllers\Admin\DashboardController;
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
    Route::post('/login', [AuthController::class, 'login'])->name('login');
    Route::post('/book-view', [BookController::class, 'storeBookView']);
    Route::post('/request-password-reset', [PasswordResetController::class, 'requestPasswordReset']);
    Route::post('/reset-password', [PasswordResetController::class, 'resetPassword']);
});

Route::apiResource('categories', CategoryController::class);

Route::get('/books', [BookWorkflowController::class, 'approvedBooks'])->name('api.books.index');
Route::get('/users', [AuthorController::class, 'index'])->name('api.users.index');
Route::get('/users/{id}', [AuthorController::class, 'show'])->whereNumber('id')->name('api.users.show');
Route::get('/authors', [AuthorController::class, 'index'])->name('api.authors.index');
Route::get('/authors/by-name/{name}', [AuthorController::class, 'showByName'])->name('api.authors.by-name');
Route::get('/authors/{id}', [AuthorController::class, 'show'])->whereNumber('id')->name('api.authors.show');
Route::get('/book', [BookWorkflowController::class, 'approvedBooks']);
Route::get('/books/discover', [BookWorkflowController::class, 'discoverBooks'])->name('api.books.discover');
Route::get('/books/{book}', [BookWorkflowController::class, 'show'])->name('api.books.show');
Route::get('/books/{book}/read', [BookWorkflowController::class, 'readPdf'])->name('api.books.read');
Route::get('/books/{book}/cover', [BookWorkflowController::class, 'viewCover'])->name('api.books.cover');
Route::post('/books/{book}/download', [BookWorkflowController::class, 'resolveDownload'])->name('api.books.download.resolve');
Route::get('/achievements', [AchievementController::class, 'index']);

// Legacy write aliases used by existing frontend clients.
Route::post('/books', [BookController::class, 'store']);
Route::patch('/books/{id}', [BookController::class, 'updateBook']);
Route::delete('/books/{id}', [BookController::class, 'deleteBook']);
Route::post('/author/upload', [BookController::class, 'store']);
Route::post('/author/books/upload', [BookController::class, 'store']);
Route::post('/author/books/create', [BookController::class, 'store']);

Route::get('/books/{book}/comments', [ReaderReviewController::class, 'listComments'])->name('api.books.comments.index');
Route::get('/books/{book}/reviews', [ReaderReviewController::class, 'listReviews'])->name('api.books.reviews.index');
Route::get('/books/{book}/ratings', [ReaderReviewController::class, 'ratings'])->name('api.books.ratings.index');

Route::apiResource('posts', PostController::class);

Route::middleware('auth:sanctum')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);

    Route::post('/books/{book}/comments', [ReaderReviewController::class, 'addComment'])->name('api.books.comments.store');
    Route::post('/books/{book}/reviews', [ReaderReviewController::class, 'createReview'])->name('api.books.reviews.store');
    Route::patch('/reviews/{id}', [ReaderReviewController::class, 'updateReview'])->name('api.reviews.update');
    Route::delete('/reviews/{id}', [ReaderReviewController::class, 'deleteReview'])->name('api.reviews.destroy');
    Route::post('/reviews/{id}/like', [ReaderReviewController::class, 'likeReview'])->name('api.reviews.like');
    Route::post('/reviews/{id}/unlike', [ReaderReviewController::class, 'unlikeReview'])->name('api.reviews.unlike');
    Route::get('/books/{book}/analytics', [AuthorBookController::class, 'analytics'])->name('api.books.analytics');
    Route::post('/books/{book}/ratings', [ReaderReviewController::class, 'rate'])->name('api.books.ratings.store');
    Route::post('/books/{book}/rating', [ReaderReviewController::class, 'rate']);

    Route::post('/reading/start', [ReadingSessionController::class, 'startReading']);
    Route::post('/reading/finish', [ReadingSessionController::class, 'finishReading']);

    Route::get('/users/{user}/achievements', [AchievementController::class, 'userAchievements']);
    Route::post('/reading-logs', [AchievementController::class, 'storeReadingLog']);
    Route::post('/users/{user}/check-achievements', [AchievementController::class, 'checkAchievements']);

    Route::get('/user/notifications', [NotificationController::class, 'userIndex']);
    Route::post('/user/notifications/{id}/read', [NotificationController::class, 'markUserAsRead']);

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
        Route::get('/', [ProfileController::class, 'getCurrentUser']);
        Route::get('/profile', [ProfileController::class, 'getCurrentUser']);
        Route::match(['post', 'patch', 'put'], '/profile', [ProfileController::class, 'updateProfile']);
        Route::post('/avatar', [ProfileController::class, 'uploadAvatar']);
        Route::get('/reading-activity', [ReadingSessionController::class, 'activity']);
    });

    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [ProfileController::class, 'getCurrentUser']);
        Route::match(['post', 'patch'], '/update-profile', [ProfileController::class, 'updateProfile']);
        Route::post('/change-password', [ProfileController::class, 'changePassword']);

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
    Route::get('/notifications', [NotificationController::class, 'authorIndex']);

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
    Route::get('/dashboard/activity', [DashboardController::class, 'activity']);
    Route::get('/dashboard/health', [DashboardController::class, 'health']);
    Route::get('/leaderboard/readers', [AdminReaderLeaderboardController::class, 'index']);
    Route::get('/monitor/summary', [AdminSystemMonitorController::class, 'summary']);
    Route::get('/monitor/activity', [AdminSystemMonitorController::class, 'activity']);
    Route::get('/monitor/health', [AdminSystemMonitorController::class, 'health']);
    Route::get('/monitor/top-books', [AdminSystemMonitorController::class, 'topBooks']);
    Route::get('/monitor/dashboard', [AdminSystemMonitorController::class, 'dashboard']);

    Route::get('/settings', [AdminSettingsController::class, 'show']);
    Route::match(['put', 'patch', 'post'], '/settings', [AdminSettingsController::class, 'changePassword']);

    Route::get('/notifications', [NotificationController::class, 'adminIndex']);
    Route::post('/notifications/send', [NotificationController::class, 'send']);

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
