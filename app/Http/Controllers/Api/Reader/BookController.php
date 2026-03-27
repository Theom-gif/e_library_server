<?php

namespace App\Http\Controllers\Api\Reader;

use App\Http\Controllers\Controller;
use App\Services\BookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookController extends Controller
{
    public function index(Request $request, BookService $bookService): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 15);
        $books = $bookService->listApproved($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Approved books retrieved successfully.',
            'data' => $books->items(),
            'meta' => [
                'current_page' => $books->currentPage(),
                'last_page' => $books->lastPage(),
                'per_page' => $books->perPage(),
                'total' => $books->total(),
            ],
        ]);
    }

    public function show(int $bookId, BookService $bookService): JsonResponse
    {
        $book = $bookService->findApproved($bookId);

        if (!$book) {
            return response()->json([
                'success' => false,
                'message' => 'Book not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Book retrieved successfully.',
            'data' => $book,
        ]);
    }
}
