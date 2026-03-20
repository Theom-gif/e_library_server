<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StartReadingSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'book_id' => 'required|integer|exists:books,id',
            'started_at' => 'nullable|date',
            'source' => 'nullable|string|max:30',
            'current_page' => 'nullable|integer|min:1',
            'progress_percent' => 'nullable|numeric|min:0|max:100',
        ];
    }
}
