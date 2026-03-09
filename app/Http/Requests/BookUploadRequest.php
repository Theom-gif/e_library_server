<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class BookUploadRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (!$this->hasFile('pdf')) {
            foreach (['manuscript', 'book_pdf', 'book_file', 'pdfFile'] as $pdfAlias) {
                if ($this->hasFile($pdfAlias)) {
                    $this->files->set('pdf', $this->file($pdfAlias));
                    break;
                }
            }
        }

        if (!$this->hasFile('pdf') && $this->hasFile('file')) {
            $singleFile = $this->file('file');
            $mimeType = strtolower((string) $singleFile?->getMimeType());
            $originalName = strtolower((string) $singleFile?->getClientOriginalName());
            $looksLikePdf = str_contains($mimeType, 'pdf') || Str::endsWith($originalName, '.pdf');

            if ($looksLikePdf) {
                $this->files->set('pdf', $singleFile);
            } elseif (!$this->hasFile('cover_image')) {
                $this->files->set('cover_image', $singleFile);
            }
        }

        if (!$this->hasFile('cover_image') && $this->hasFile('cover')) {
            $this->files->set('cover_image', $this->file('cover'));
        }

        if (!$this->hasFile('cover_image')) {
            foreach (['thumbnail', 'image', 'coverImage'] as $coverAlias) {
                if ($this->hasFile($coverAlias)) {
                    $this->files->set('cover_image', $this->file($coverAlias));
                    break;
                }
            }
        }

        if (!$this->has('category') && $this->has('genre')) {
            $this->merge([
                'category' => $this->input('genre'),
            ]);
        }

        if (!$this->has('total_pages') && $this->has('totalPages')) {
            $this->merge([
                'total_pages' => $this->input('totalPages'),
            ]);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'author_name' => 'nullable|string|max:255',
            'category_id' => 'nullable|integer|exists:categories,id',
            'category' => 'nullable|string|max:120|required_without:category_id',
            'language' => 'nullable|string|max:12',
            'total_pages' => 'nullable|integer|min:1',
            'pdf' => 'required|file|mimes:pdf|max:51200',
            'cover_image' => 'nullable|image|max:5120',
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Title is required.',
            'category.required_without' => 'Category or category_id is required.',
            'pdf.required' => 'Book PDF file is required.',
            'pdf.mimes' => 'Only PDF files are allowed for book upload.',
        ];
    }
}
