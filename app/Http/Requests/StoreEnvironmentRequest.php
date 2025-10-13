<?php

declare(strict_types=1);

namespace Phlag\Http\Requests;

use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Phlag\Models\Project;
use RuntimeException;

class StoreEnvironmentRequest extends FormRequest
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
                Rule::unique('environments', 'key')
                    ->where(static function (Builder $query) use ($project): Builder {
                        return $query->where('project_id', $project->id);
                    }),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_default' => ['sometimes', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
