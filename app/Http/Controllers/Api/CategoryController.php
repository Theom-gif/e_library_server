<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        if (!Category::query()->exists()) {
            Category::create([
                'name' => 'General',
                'slug' => 'general',
                'description' => 'Default category',
                'is_active' => true,
            ]);
        }

        $query = Category::query()
            ->withCount([
                'books as books_count' => function ($bookQuery) {
                    $bookQuery->where('status', 'approved');
                },
            ])
            ->orderBy('name');

        if ($request->query('active_only', '1') !== '0') {
            $query->where('is_active', true);
        }

        return response()->json([
            'success' => true,
            'message' => 'Categories retrieved successfully',
            'data' => $query->get()->map(fn (Category $category) => $this->transform($category)),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:categories,name'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:categories,slug'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $name = trim($validated['name']);
        $slug = $validated['slug'] ?? Str::slug($name);
        if ($slug === '') {
            $slug = 'category';
        }

        $category = Category::create([
            'name' => $name,
            'slug' => $this->generateUniqueSlug($slug),
            'description' => $validated['description'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        $category->loadCount([
            'books as books_count' => function ($bookQuery) {
                $bookQuery->where('status', 'approved');
            },
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Category created successfully',
            'data' => $this->transform($category),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $category = Category::query()->find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found',
            ], 404);
        }

        $category->loadCount([
            'books as books_count' => function ($bookQuery) {
                $bookQuery->where('status', 'approved');
            },
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Category retrieved successfully',
            'data' => $this->transform($category),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $category = Category::query()->find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found',
            ], 404);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('categories', 'name')->ignore($category->id)],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255', Rule::unique('categories', 'slug')->ignore($category->id)],
            'description' => ['sometimes', 'nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('name', $validated)) {
            $validated['name'] = trim($validated['name']);
        }

        if (array_key_exists('slug', $validated)) {
            $candidate = trim((string) $validated['slug']);
            $validated['slug'] = $candidate === '' ? Str::slug($category->name) : Str::slug($candidate);
        } elseif (array_key_exists('name', $validated)) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        if (array_key_exists('slug', $validated)) {
            $validated['slug'] = $this->generateUniqueSlug($validated['slug'], $category->id);
        }

        $category->update($validated);

        $category = $category->fresh();
        $category->loadCount([
            'books as books_count' => function ($bookQuery) {
                $bookQuery->where('status', 'approved');
            },
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Category updated successfully',
            'data' => $this->transform($category),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $category = Category::query()->find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found',
            ], 404);
        }

        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Category deleted successfully',
        ]);
    }

    private function generateUniqueSlug(string $baseSlug, ?int $ignoreId = null): string
    {
        $slug = Str::slug($baseSlug);
        if ($slug === '') {
            $slug = 'category';
        }

        $candidate = $slug;
        $counter = 2;

        while (
            Category::query()
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->where('slug', $candidate)
                ->exists()
        ) {
            $candidate = $slug.'-'.$counter;
            $counter++;
        }

        return $candidate;
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(Category $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'is_active' => (bool) $category->is_active,
            'count' => (int) ($category->books_count ?? 0),
            'books_count' => (int) ($category->books_count ?? 0),
        ];
    }
}
