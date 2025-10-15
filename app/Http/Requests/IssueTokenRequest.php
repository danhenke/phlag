<?php

declare(strict_types=1);

namespace Phlag\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class IssueTokenRequest extends FormRequest
{
    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'project' => is_string($this->input('project')) ? trim((string) $this->input('project')) : $this->input('project'),
            'environment' => is_string($this->input('environment')) ? trim((string) $this->input('environment')) : $this->input('environment'),
            'api_key' => is_string($this->input('api_key')) ? trim((string) $this->input('api_key')) : $this->input('api_key'),
        ]);
    }

    public function authorize(): true
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>|string>
     */
    public function rules(): array
    {
        return [
            'project' => ['required', 'string', 'max:255'],
            'environment' => ['required', 'string', 'max:255'],
            'api_key' => ['required', 'string', 'min:12', 'max:255'],
        ];
    }
}
