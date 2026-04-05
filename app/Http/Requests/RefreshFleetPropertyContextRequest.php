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
}
