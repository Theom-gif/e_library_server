<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RateBookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'book_id' => 'required|integer|exists:books,id',
            'rating' => 'required|numeric|min:1|max:5',
            'review' => 'nullable|string|max:2000',
        ];
    }
}
