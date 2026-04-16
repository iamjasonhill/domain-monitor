<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RecordAstroCutoverRequest extends FormRequest
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
            'astro_cutover_at' => ['sometimes', 'date'],
            'refresh_seo_baseline' => ['sometimes', 'boolean'],
            'captured_by' => ['sometimes', 'string', 'max:120'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'astro_cutover_at.date' => 'The Astro cutover timestamp must be a valid date or ISO-8601 timestamp.',
            'refresh_seo_baseline.boolean' => 'The refresh SEO baseline flag must be true or false.',
            'captured_by.string' => 'The captured-by label must be plain text.',
            'captured_by.max' => 'The captured-by label may not be greater than 120 characters.',
            'notes.string' => 'The cutover notes must be plain text.',
            'notes.max' => 'The cutover notes may not be greater than 1000 characters.',
        ];
    }
}
