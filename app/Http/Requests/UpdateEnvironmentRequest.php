<?php

declare(strict_types=1);

namespace Phlag\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Phlag\Models\Environment;
use Phlag\Models\Project;

class UpdateEnvironmentRequest extends FormRequest
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
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Project $project */
        $project = $this->route('project');

        /** @var Environment $environment */
        $environment = $this->route('environment');

        return [
            'key' => [
                'sometimes',
                'string',
                'max:64',
                'regex:/^[a-z0-9][a-z0-9-]*$/',
                Rule::unique('environments', 'key')
                    ->where(fn ($query) => $query->where('project_id', $project->id))
                    ->ignore($environment->id),
            ],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'is_default' => ['sometimes', 'boolean'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
