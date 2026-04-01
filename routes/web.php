<?php

use App\Http\Controllers\Api\ProfileController;
use Illuminate\Support\Facades\Route;


Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'timestamp' => now()->toIso8601String(),
    ]);
});

Route::get('/avatars/{userId}', [ProfileController::class, 'showAvatar'])->name('avatars.show');
Route::get('/avatar/{userId}', [ProfileController::class, 'showAvatar']);
