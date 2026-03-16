<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProgressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'book_id' => 'required|integer|exists:books,id',
            'progress_percent' => 'nullable|numeric|min:0|max:100',
            'current_page' => 'nullable|integer|min:0',
            'is_completed' => 'nullable|boolean',
        ];
    }
}
