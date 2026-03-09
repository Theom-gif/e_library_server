<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\admin\category\BookUploadRequest;
use App\Models\Book;
use Illuminate\Http\JsonResponse;

class BookController extends Controller
{
    /**
     * Upload and store a new book.
     */
    public function store(BookUploadRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $coverFile = $request->file('cover_image') ?? $request->file('coverImage');
            $bookFile = $request->file('book_file') ?? $request->file('bookFile');

            if (!isset($validated['user_id']) && $request->user()) {
                $validated['user_id'] = $request->user()->id;
            }

            $book = Book::createFromUpload($validated, $coverFile, $bookFile);
            $bookArray = $book->toApiArray();

            return response()->json([
                'success' => true,
                'message' => 'Book uploaded successfully',
                'data' => $bookArray,
                'book' => $bookArray,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload book',
                'errors' => $e->getMessage(),
            ], 500);
        }
    }
}
