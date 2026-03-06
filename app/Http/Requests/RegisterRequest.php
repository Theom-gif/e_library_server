<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (!$this->has('firstname')) {
            $this->merge([
                'firstname' => $this->input('first_name', $this->input('firstName')),
            ]);
        }

        if (!$this->has('lastname')) {
            $this->merge([
                'lastname' => $this->input('last_name', $this->input('lastName')),
            ]);
        }

        if (!$this->has('password_confirmation')) {
            $this->merge([
                'password_confirmation' => $this->input(
                    'password_confirm',
                    $this->input('confirm_password', $this->input('confirmPassword', $this->input('passwordConfirmation')))
                ),
            ]);
        }

        if (!$this->has('role_id') && $this->has('roleId')) {
            $this->merge([
                'role_id' => $this->input('roleId'),
            ]);
        }

        // Default normal registration to the User role when client omits role.
        $roleInput = $this->input('role_id', $this->input('role_name', $this->input('role', 'User')));
        if ($roleInput === null || $roleInput === '') {
            return;
        }

        if (is_numeric($roleInput)) {
            $this->merge(['role_id' => (int) $roleInput]);
            return;
        }

        $roleId = DB::table('roles')
            ->whereRaw('LOWER(name) = ?', [strtolower(trim((string) $roleInput))])
            ->value('id');

        if ($roleId !== null) {
            $this->merge(['role_id' => (int) $roleId]);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

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
            'role_id' => 'required|integer|exists:roles,id',
        ];
    }

    public function messages(): array
    {
        return [
            'firstname.required' => 'First name is required.',
            'lastname.required' => 'Last name is required.',
            'email.required' => 'Email is required.',
            'email.email' => 'Please provide a valid email.',
            'email.unique' => 'This email is already registered.',
            'password.required' => 'Password is required.',
            'password.confirmed' => 'Passwords do not match.',
            'role_id.required' => 'Role is required.',
            'role_id.exists' => 'The selected role is invalid. Use User, Author, or Admin.',
        ];
    }
}