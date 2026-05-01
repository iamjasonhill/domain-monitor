<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class LiveSeoVerificationPacketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'url' => ['nullable', 'url', 'required_without:url_pattern'],
            'url_pattern' => ['nullable', 'string', 'max:500', 'required_without:url'],
            'sample_url' => ['nullable', 'url', 'required_with:url_pattern'],
            'timeout' => ['sometimes', 'integer', 'min:1', 'max:15'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'url.required_without' => 'Provide either a verification URL or a URL pattern.',
            'url.url' => 'The verification URL must be a valid URL.',
            'url_pattern.required_without' => 'Provide either a URL pattern or an exact verification URL.',
            'url_pattern.max' => 'The URL pattern may not be greater than 500 characters.',
            'sample_url.required_with' => 'A sample URL is required when verifying a URL pattern.',
            'sample_url.url' => 'The sample URL must be a valid URL.',
            'timeout.integer' => 'The timeout must be a whole number of seconds.',
            'timeout.min' => 'The timeout must be at least 1 second.',
            'timeout.max' => 'The timeout may not be greater than 15 seconds.',
        ];
    }
}
