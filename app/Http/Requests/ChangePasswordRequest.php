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
        if (!$this->filled('current_password')) {
            $this->merge([
                'current_password' => $this->input(
                    'currentPassword',
                    $this->input(
                        'old_password',
                        $this->input('oldPassword', $this->input('current'))
                    )
                ),
            ]);
        }

        if (!$this->filled('new_password')) {
            $this->merge([
                'new_password' => $this->input(
                    'newPassword',
                    $this->input('password', $this->input('new', $this->input('new_pass')))
                ),
            ]);
        }

        if (!$this->filled('new_password_confirmation')) {
            $this->merge([
                'new_password_confirmation' => $this->input(
                    'new_password_confirm',
                    'newPasswordConfirmation',
                    $this->input(
                        'confirmNewPassword',
                        $this->input(
                            'confirm_new_password',
                            $this->input(
                                'confirm_password',
                                $this->input('confirmPassword', $this->input('password_confirmation'))
                            )
                        )
                    )
                ),
            ]);
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
