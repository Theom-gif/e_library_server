<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class HeartbeatReadingSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'occurred_at' => 'nullable|date',
            'seconds_since_last_ping' => 'nullable|integer|min:1|max:3600',
            'progress_percent' => 'nullable|numeric|min:0|max:100',
            'current_page' => 'nullable|integer|min:1',
        ];
    }
}
