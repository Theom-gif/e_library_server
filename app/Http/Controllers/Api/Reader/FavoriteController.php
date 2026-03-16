<?php

namespace App\Http\Controllers\Api\Reader;

use App\Http\Controllers\Controller;
use App\Models\Favorite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    public function toggle(Request $request): JsonResponse
    {
        $data = $request->validate([
            'book_id' => 'required|integer|exists:books,id',
        ]);

        $user = $request->user();

        $existing = Favorite::query()
            ->where('user_id', $user?->id)
            ->where('book_id', $data['book_id'])
            ->first();

        if ($existing) {
            $existing->delete();

            return response()->json([
                'success' => true,
                'message' => 'Removed from favorites.',
                'data' => ['is_favorite' => false],
            ]);
        }

        Favorite::create([
            'user_id' => $user?->id,
            'book_id' => $data['book_id'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Added to favorites.',
            'data' => ['is_favorite' => true],
        ], 201);
    }
}
