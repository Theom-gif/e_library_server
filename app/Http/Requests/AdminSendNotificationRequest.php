<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdminSendNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (int) ($this->user()?->role_id ?? 0) === 1;
    }

    public function rules(): array
    {
        return [
            'target' => ['required', 'string', 'in:all,user,author,admin'],
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
            'data' => ['nullable', 'array'],
        ];
    }
}
