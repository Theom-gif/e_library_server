<?php

namespace App\Http\Requests\author;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookRequest extends FormRequest
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
            'title' => 'required|string|max:255',
            'author' => 'nullable|string|max:255',
            'category' => 'required|string|max:120',
            'description' => 'nullable|string',
            'first_publish_year' => 'nullable|integer|min:1000|max:'.$maxYear,
            'cover_image' => 'nullable|image|max:5120',
            'book_file' => 'required_without:book_file_url|file|mimes:pdf,epub,doc,docx|max:51200',
            'book_file_url' => 'nullable|string|max:2048',
            'cover_image_url' => 'nullable|string|max:2048',
            'category_id' => 'nullable|integer|exists:categories,id',
        ];
    }
}
