<?php

namespace App\Http\Requests\admin\category;

use Illuminate\Foundation\Http\FormRequest;

class BookUploadRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'author' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'category' => 'nullable|string|max:255',
            'published_year' => 'nullable|integer|min:1000|max:' . (now()->year + 1),
            'user_id' => 'nullable|integer|exists:users,id',
            'cover_image' => 'nullable|image|max:5120',
            'book_file' => 'nullable|file|mimes:pdf,epub,doc,docx|max:20480|required_without:book_file_url',
            'cover_image_url' => 'nullable|url|max:2048',
            'book_file_url' => 'nullable|url|max:2048|required_without:book_file',
        ];
    }
}
