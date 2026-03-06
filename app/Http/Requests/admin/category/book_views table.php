<?php

namespace App\Http\Requests\Admin\Category;

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

    /**
     * Get custom error messages.
     */
    public function messages(): array
    {
        return [
            'book_id.required' => 'Book ID is required.',
            'book_id.integer' => 'Book ID must be a number.',
            'user_id.exists' => 'Selected user does not exist.',
            'ip_address.ip' => 'IP address format is invalid.',
            'viewed_at.date' => 'Viewed at must be a valid date.',
        ];
    }
}
