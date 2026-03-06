<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\DB;

class RegisterRequest extends FormRequest
{
    /**
     * Normalize legacy confirmation fields.
     */
    protected function prepareForValidation(): void
    {
        if (!$this->has('password_confirmation') && $this->has('password_confirm')) {
            $this->merge(['password_confirmation' => $this->input('password_confirm')]);
        }

        $roleInput = $this->input('role_id', $this->input('role_name', $this->input('role')));
        if ($roleInput === null || $roleInput === '') {
            return;
        }

        if (is_numeric($roleInput)) {
            $this->merge(['role_id' => (int) $roleInput]);
            return;
        }

        $roleId = DB::table('roles')
            ->whereRaw('LOWER(role_name) = ?', [strtolower(trim((string) $roleInput))])
            ->value('role_id');

        if ($roleId !== null) {
            $this->merge(['role_id' => (int) $roleId]);
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
            'firstname' => 'required|string|max:255',
            'lastname' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => [
                'required',
                'confirmed',
                Password::min(8)
                    ->mixedCase()
                    ->numbers()
                    ->symbols(),
            ],
            'role_id' => 'required|integer|exists:roles,role_id',
        ];
    }

    /**
     * Get custom error messages.
     */
    public function messages(): array
    {
        return [
            'firstname.required' => 'First name is required',
            'lastname.required' => 'Last name is required',
            'email.unique' => 'This email is already registered',
            'password.confirmed' => 'Passwords do not match',
            'role_id.exists' => 'The selected role is invalid. Use User, Author, or Admin.',
        ];
    }
}
