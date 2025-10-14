<?php

declare(strict_types=1);

namespace Phlag\Http\Requests;

use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Phlag\Models\Project;
use RuntimeException;

class StoreFlagRequest extends FormRequest
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
        $project = $this->route('project');

        if (! $project instanceof Project) {
            throw new RuntimeException('Project route binding missing.');
        }

        return [
            'key' => [
                'required',
                'string',
                'max:64',
                'regex:/^[a-z0-9][a-z0-9-]*$/',
                Rule::unique('flags', 'key')->where(
                    fn (QueryBuilder $query) => $query->where('project_id', $project->id)
                ),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_enabled' => ['sometimes', 'boolean'],
            'variants' => ['required', 'array', 'min:1'],
            'variants.*' => ['array'],
            'variants.*.key' => [
                'required',
                'string',
                'max:64',
                'regex:/^[a-z0-9][a-z0-9_-]*$/',
                'distinct',
            ],
            'variants.*.weight' => ['nullable', 'integer', 'between:0,100'],
            'rules' => ['nullable', 'array'],
            'rules.*' => ['array'],
            'rules.*.match' => ['required', 'array', 'min:1'],
            'rules.*.match.*' => ['array', 'min:1'],
            'rules.*.match.*.*' => ['string', 'max:255'],
            'rules.*.variant' => ['required', 'string', 'max:255'],
            'rules.*.rollout' => ['nullable', 'integer', 'between:0,100'],
        ];
    }
}
