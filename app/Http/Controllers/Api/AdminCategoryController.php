<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AdminCategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));

        $query = Category::query()->withCount('books')->orderBy('name');

        if ($search !== '') {
            $keyword = strtolower($search);
            $query->whereRaw('LOWER(name) LIKE ?', ['%'.$keyword.'%']);
        }

        $categories = $query->get()->map(fn (Category $category) => $this->transform($category));

        return response()->json([
            'data' => $categories,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255', 'unique:categories,name'],
            'icon' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 400);
        }

        $validated = $validator->validated();
        $name = trim($validated['name']);
        $slug = Str::slug($name);
        if ($slug === '') {
            $slug = 'category';
        }

        $category = Category::create([
            'name' => $name,
            'slug' => $this->generateUniqueSlug($slug),
            'icon' => $validated['icon'] ?? null,
            'is_active' => true,
        ]);

        $category->loadCount('books');

        return response()->json($this->transform($category), 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(Category $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'count' => (int) ($category->books_count ?? 0),
            'icon' => $category->icon,
        ];
    }

    private function generateUniqueSlug(string $baseSlug): string
    {
        $slug = Str::slug($baseSlug);
        if ($slug === '') {
            $slug = 'category';
        }

        $candidate = $slug;
        $counter = 2;

        while (Category::query()->where('slug', $candidate)->exists()) {
            $candidate = $slug.'-'.$counter;
            $counter++;
        }

        return $candidate;
    }
}
