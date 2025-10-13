<?php

declare(strict_types=1);

namespace Phlag\Http\Requests;

use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Phlag\Models\Environment;
use Phlag\Models\Project;
use RuntimeException;

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
        $project = $this->route('project');

        $environment = $this->route('environment');

        if (! $project instanceof Project) {
            throw new RuntimeException('Project route binding missing.');
        }

        if (! $environment instanceof Environment) {
            throw new RuntimeException('Environment route binding missing.');
        }

        return [
            'key' => [
                'sometimes',
                'string',
                'max:64',
                'regex:/^[a-z0-9][a-z0-9-]*$/',
                Rule::unique('environments', 'key')
                    ->where(static function (Builder $query) use ($project): Builder {
                        return $query->where('project_id', $project->id);
                    })
                    ->ignore($environment->id),
            ],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'is_default' => ['sometimes', 'boolean'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
