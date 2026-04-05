<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RefreshFleetPropertyContextRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'force_search_console_api_enrichment' => ['sometimes', 'boolean'],
            'search_console_stale_days' => ['sometimes', 'integer', 'min:1', 'max:30'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'force_search_console_api_enrichment.boolean' => 'The force Search Console refresh flag must be true or false.',
            'search_console_stale_days.integer' => 'The Search Console stale-days override must be a whole number.',
            'search_console_stale_days.min' => 'The Search Console stale-days override must be at least 1 day.',
            'search_console_stale_days.max' => 'The Search Console stale-days override may not be greater than 30 days.',
        ];
    }
}
