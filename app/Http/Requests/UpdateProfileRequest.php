<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
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
            'firstname' => 'sometimes|string|max:255',
            'lastname' => 'sometimes|string|max:255',
            'bio' => 'sometimes|nullable|string|max:1000',
            'facebook_url' => 'sometimes|nullable|url',
            'avatar' => 'sometimes|nullable|string',
            'avatar_file' => 'sometimes|nullable|image|max:5120',
        ];
    }

    /**
     * Get custom error messages.
     */
    public function messages(): array
    {
        return [
            'facebook_url.url' => 'Please provide a valid URL for your Facebook profile',
            'bio.max' => 'Bio must not exceed 1000 characters',
        ];
    }
}
