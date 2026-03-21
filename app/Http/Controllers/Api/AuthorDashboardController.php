<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AuthorDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AuthorDashboardController extends Controller
{
    public function __construct(
        private readonly AuthorDashboardService $dashboardService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'range' => ['nullable', Rule::in(['30d', '6m', '12m'])],
            'top_books_limit' => 'nullable|integer|min:1|max:20',
            'feedback_limit' => 'nullable|integer|min:1|max:20',
        ]);

        $payload = $this->dashboardService->getDashboard(
            (int) $request->user()->id,
            $validated['range'] ?? '6m',
            (int) ($validated['top_books_limit'] ?? 5),
            (int) ($validated['feedback_limit'] ?? 5)
        );

        return response()->json($payload);
    }

    public function stats(Request $request): JsonResponse
    {
        return response()->json(
            $this->dashboardService->getStats((int) $request->user()->id)
        );
    }

    public function performance(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'range' => ['nullable', Rule::in(['30d', '6m', '12m'])],
        ]);

        return response()->json(
            $this->dashboardService->getPerformance(
                (int) $request->user()->id,
                $validated['range'] ?? '6m'
            )
        );
    }

    public function topBooks(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'limit' => 'nullable|integer|min:1|max:20',
        ]);

        return response()->json(
            $this->dashboardService->getTopBooks(
                (int) $request->user()->id,
                (int) ($validated['limit'] ?? 5)
            )
        );
    }

    public function feedback(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'limit' => 'nullable|integer|min:1|max:20',
        ]);

        return response()->json(
            $this->dashboardService->getFeedback(
                (int) $request->user()->id,
                (int) ($validated['limit'] ?? 5)
            )
        );
    }

    public function demographics(Request $request): JsonResponse
    {
        return response()->json(
            $this->dashboardService->getDemographics((int) $request->user()->id)
        );
    }
}
