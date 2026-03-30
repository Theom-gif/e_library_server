<?php

namespace App\Http\Requests\admin\category;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Models\User;

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
            'title' => 'nullable|string|max:255',
            'author' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'category' => 'nullable|string|max:255',
            'published_year' => 'nullable|integer|min:1000|max:' . (now()->year + 1),
            'user_id' => 'nullable|integer',
            'cover_image' => 'nullable|image|max:10240',
            'coverImage' => 'nullable|image|max:10240',
            'book_file' => 'nullable|file|max:51200',
            'bookFile' => 'nullable|file|max:51200',
            'cover_image_url' => 'nullable|string|max:2048',
            'coverImageUrl' => 'nullable|string|max:2048',
            'book_file_url' => 'nullable|string|max:4096',
            'bookFileUrl' => 'nullable|string|max:4096',
        ];
    }

    protected function prepareForValidation(): void
    {
        $inputAliases = [
            'bookTitle' => 'title',
            'book_name' => 'title',
            'bookName' => 'title',
            'name' => 'title',
            'authorName' => 'author',
            'summary' => 'description',
            'desc' => 'description',
            'genre' => 'category',
            'publishedYear' => 'published_year',
            'userId' => 'user_id',
            'coverImageUrl' => 'cover_image_url',
            'bookFileUrl' => 'book_file_url',
            'fileUrl' => 'book_file_url',
            'book_file_path' => 'book_file_url',
            'bookFilePath' => 'book_file_url',
        ];

        $merged = [];
        foreach ($inputAliases as $from => $to) {
            if (!$this->filled($to) && $this->has($from)) {
                $merged[$to] = $this->input($from);
            }
        }

        if (!empty($merged)) {
            $this->merge($merged);
        }

        $fileAliases = [
            'coverImage' => 'cover_image',
            'cover' => 'cover_image',
            'image' => 'cover_image',
            'bookFile' => 'book_file',
            'file' => 'book_file',
            'book' => 'book_file',
            'pdf' => 'book_file',
        ];

        foreach ($fileAliases as $from => $to) {
            if (!$this->hasFile($to) && $this->hasFile($from)) {
                $this->files->set($to, $this->file($from));
            }
        }

        $userId = $this->input('user_id');
        if ($userId !== null && $userId !== '' && !User::query()->whereKey($userId)->exists()) {
            $this->merge(['user_id' => null]);
        }

        if (!$this->filled('title')) {
            $fallbackTitle = null;
            if ($this->hasFile('book_file')) {
                $fallbackTitle = pathinfo($this->file('book_file')->getClientOriginalName(), PATHINFO_FILENAME);
            } elseif ($this->filled('book_file_url')) {
                $fallbackTitle = basename((string) parse_url((string) $this->input('book_file_url'), PHP_URL_PATH));
            }

            $fallbackTitle = trim((string) $fallbackTitle);
            if ($fallbackTitle === '') {
                $fallbackTitle = 'Untitled Book';
            }

            $this->merge(['title' => mb_substr($fallbackTitle, 0, 255)]);
        }
    }

    public function messages(): array
    {
        return [
            'book_file.max' => 'Book file is too large (max 50MB).',
            'bookFile.max' => 'Book file is too large (max 40MB).',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $validator->errors(),
        ], 422));
    }
}
