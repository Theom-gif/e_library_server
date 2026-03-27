<?php

namespace App\Http\Controllers\Api\Reader;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\OfflineDownload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DownloadController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $downloads = OfflineDownload::query()
            ->with('book')
            ->where('user_id', $request->user()->id)
            ->latest('downloaded_at')
            ->get()
            ->map(function (OfflineDownload $download) {
                return [
                    'id' => $download->id,
                    'downloaded_at' => $download->downloaded_at?->toIso8601String(),
                    'sync_status' => $download->sync_status,
                    'book' => $download->book?->toApiArray(),
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $downloads,
        ]);
    }

    public function store(Request $request, Book $book): JsonResponse
    {
        if ($book->status !== 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Book not found.',
            ], 404);
        }

        $download = OfflineDownload::query()->updateOrCreate(
            [
                'book_id' => $book->id,
                'user_id' => $request->user()->id,
            ],
            [
                'local_identifier' => $request->input('local_identifier'),
                'downloaded_at' => now(),
                'last_synced_at' => now(),
                'sync_status' => 'synced',
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Download recorded successfully.',
            'data' => [
                'id' => $download->id,
                'book_id' => $download->book_id,
                'downloaded_at' => $download->downloaded_at?->toIso8601String(),
            ],
        ], 201);
    }
}
