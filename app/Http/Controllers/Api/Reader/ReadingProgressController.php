<?php

namespace App\Http\Controllers\Api\Reader;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProgressRequest;
use App\Models\ReadingProgress;
use Illuminate\Http\JsonResponse;

class ReadingProgressController extends Controller
{
    public function update(UpdateProgressRequest $request): JsonResponse
    {
        $user = $request->user();

        $progress = ReadingProgress::updateOrCreate(
            [
                'user_id' => $user?->id,
                'book_id' => $request->book_id,
            ],
            [
                'progress_percent' => $request->input('progress_percent'),
                'current_page' => $request->input('current_page'),
                'is_completed' => $request->input('is_completed'),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Reading progress updated successfully.',
            'data' => $progress,
        ]);
    }
}
