<?php

namespace App\Http\Requests\author;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBookRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (!$this->has('category') && $this->has('genre')) {
            $this->merge([
                'category' => $this->input('genre'),
            ]);
        }

        if (!$this->has('category_id') && $this->has('categoryId')) {
            $this->merge([
                'category_id' => $this->input('categoryId'),
            ]);
        }

        if (!$this->has('author') && $this->has('authorName')) {
            $this->merge([
                'author' => $this->input('authorName'),
            ]);
        }

        if (!$this->has('book_file_url') && $this->has('bookFileUrl')) {
            $this->merge([
                'book_file_url' => $this->input('bookFileUrl'),
            ]);
        }

        if (!$this->has('cover_image_url') && $this->has('coverImageUrl')) {
            $this->merge([
                'cover_image_url' => $this->input('coverImageUrl'),
            ]);
        }

        if (!$this->hasFile('cover_image') && $this->hasFile('coverImage')) {
            $this->files->set('cover_image', $this->file('coverImage'));
        }

        if (!$this->hasFile('book_file') && $this->hasFile('bookFile')) {
            $this->files->set('book_file', $this->file('bookFile'));
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxYear = now()->year + 1;

        return [
                'title' => 'sometimes|required|string|max:255',
                'author' => 'sometimes|nullable|string|max:255',
                'category' => 'sometimes|nullable|string|max:120',
                'description' => 'sometimes|nullable|string',
                'first_publish_year' => 'sometimes|nullable|integer|min:1000|max:'.$maxYear,
                'cover_image' => 'sometimes|nullable|image|max:5120',
                'book_file' => 'sometimes|nullable|file|mimes:pdf,epub,doc,docx|max:51200',
                'book_file_url' => 'sometimes|nullable|string|max:2048',
                'cover_image_url' => 'sometimes|nullable|string|max:2048',
                'category_id' => 'sometimes|nullable|integer|exists:categories,id',
        ];
    }
}
