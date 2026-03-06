<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ChangePasswordRequest extends FormRequest
{
    /**
     * Normalize legacy confirmation fields.
     */
    protected function prepareForValidation(): void
    {
        if (!$this->has('new_password_confirmation') && $this->has('new_password_confirm')) {
            $this->merge(['new_password_confirmation' => $this->input('new_password_confirm')]);
        }
    }

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
            'current_password' => 'required|string',
            'new_password' => [
                'required',
                'confirmed',
                Password::min(8)
                    ->mixedCase()
                    ->numbers()
                    ->symbols(),
            ],
        ];
    }

    /**
     * Get custom error messages.
     */
    public function messages(): array
    {
        return [
            'current_password.required' => 'Current password is required',
            'new_password.confirmed' => 'Passwords do not match',
        ];
    }
}
