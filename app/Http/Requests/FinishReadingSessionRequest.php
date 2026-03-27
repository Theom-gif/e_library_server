<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FinishReadingSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ended_at' => 'nullable|date',
            'progress_percent' => 'nullable|numeric|min:0|max:100',
            'current_page' => 'nullable|integer|min:1',
        ];
    }
}
