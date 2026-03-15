<?php

namespace App\Http\Requests\admin\category;

use Illuminate\Foundation\Http\FormRequest;

class BookViewRequest extends FormRequest
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
            'book_id' => 'required|integer|min:1',
            'user_id' => 'nullable|integer|exists:users,id',
            'ip_address' => 'nullable|ip',
            'user_agent' => 'nullable|string|max:1000',
            'viewed_at' => 'nullable|date',
        ];
    }
}
