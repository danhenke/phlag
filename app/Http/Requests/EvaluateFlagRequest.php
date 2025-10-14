<?php

declare(strict_types=1);

namespace Phlag\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EvaluateFlagRequest extends FormRequest
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
        return [
            'project' => ['required', 'string', 'max:64'],
            'env' => ['required', 'string', 'max:64'],
            'flag' => ['required', 'string', 'max:128'],
            'user_id' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function projectKey(): string
    {
        $project = $this->query('project');

        if (! is_string($project)) {
            throw new \InvalidArgumentException('Expected validated project key to be a string.');
        }

        return $project;
    }

    public function environmentKey(): string
    {
        $environment = $this->query('env');

        if (! is_string($environment)) {
            throw new \InvalidArgumentException('Expected validated environment key to be a string.');
        }

        return $environment;
    }

    public function flagKey(): string
    {
        $flag = $this->query('flag');

        if (! is_string($flag)) {
            throw new \InvalidArgumentException('Expected validated flag key to be a string.');
        }

        return $flag;
    }

    public function userIdentifier(): ?string
    {
        $value = $this->query('user_id');

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function contextAttributes(): array
    {
        $context = [];
        $reserved = ['project', 'env', 'flag', 'user_id'];

        /** @var array<string, mixed> $query */
        $query = $this->query();

        foreach ($query as $key => $value) {
            if (! is_string($key) || in_array($key, $reserved, true)) {
                continue;
            }

            if (is_array($value)) {
                $values = array_values(array_filter(
                    array_map(
                        static fn ($item): ?string => is_scalar($item) ? (string) $item : null,
                        $value
                    ),
                    static fn ($item): bool => $item !== null && $item !== ''
                ));

                if ($values !== []) {
                    $context[$key] = $values;
                }

                continue;
            }

            if ($value === null || $value === '') {
                continue;
            }

            if (is_scalar($value)) {
                $context[$key] = [(string) $value];
            }
        }

        return $context;
    }

    /**
     * @return array<string, string|array<int, string>>
     */
    public function denormalizedContext(): array
    {
        $denormalized = [];

        foreach ($this->contextAttributes() as $key => $values) {
            $values = array_values(array_unique($values));

            if ($values === []) {
                continue;
            }

            $denormalized[$key] = count($values) === 1 ? $values[0] : $values;
        }

        return $denormalized;
    }
}
