<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BookReviewRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (!$this->has('status') && $this->has('action')) {
            $this->merge([
                'status' => $this->input('action'),
            ]);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => 'required|string|in:approved,rejected',
            'rejection_reason' => 'nullable|string|required_if:status,rejected|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Review status is required.',
            'status.in' => 'Status must be approved or rejected.',
            'rejection_reason.required_if' => 'Rejection reason is required when rejecting a book.',
        ];
    }
}
