<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'app' => 'e_library_server',
        'status' => 'running',
        'api_health' => url('/api/health'),
    ]);
});
