<?php

namespace App\Http\Controllers\Api\Reader;

use App\Http\Controllers\Controller;
use App\Http\Requests\RateBookRequest;
use App\Models\Rating;
use Illuminate\Http\JsonResponse;

class RatingController extends Controller
{
    public function store(RateBookRequest $request): JsonResponse
    {
        $user = $request->user();

        $rating = Rating::updateOrCreate(
            [
                'user_id' => $user?->id,
                'book_id' => $request->book_id,
            ],
            [
                'rating' => $request->rating,
                'review' => $request->input('review'),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Rating saved successfully.',
            'data' => $rating,
        ], 201);
    }
}
