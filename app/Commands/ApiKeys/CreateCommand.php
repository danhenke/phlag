<?php

declare(strict_types=1);

namespace Phlag\Commands\ApiKeys;

use Carbon\CarbonImmutable;
use Exception;
use Illuminate\Support\Str;
use LaravelZero\Framework\Commands\Command;
use Phlag\Auth\ApiKeys\ApiCredentialHasher;
use Phlag\Auth\ApiKeys\TokenExchangeService;
use Phlag\Models\ApiCredential;
use Phlag\Models\Environment;
use Phlag\Models\Project;
use Phlag\Support\Clock\Clock;

use function array_filter;
use function array_map;
use function array_unique;
use function array_values;
use function explode;
use function implode;
use function is_string;
use function sprintf;
use function trim;

final class CreateCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'api-key:create';

    /**
     * @var string
     */
    protected $description = 'Provision a new API key for a project environment.';

    private const DEFAULT_SCOPES = TokenExchangeService::DEFAULT_ROLES;

    public function __construct(
        private readonly Clock $clock
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $projectKey = $this->promptForRequiredString('Project key');

        if ($projectKey === null) {
            return self::FAILURE;
        }

        /** @var Project|null $project */
        $project = Project::query()->where('key', $projectKey)->first();

        if ($project === null) {
            $this->error(sprintf('Project [%s] was not found.', $projectKey));

            return self::FAILURE;
        }

        $environmentKey = $this->promptForRequiredString('Environment key');

        if ($environmentKey === null) {
            return self::FAILURE;
        }

        /** @var Environment|null $environment */
        $environment = Environment::query()
            ->where('project_id', $project->id)
            ->where('key', $environmentKey)
            ->first();

        if ($environment === null) {
            $this->error(sprintf(
                'Environment [%s] does not exist for project [%s].',
                $environmentKey,
                $projectKey
            ));

            return self::FAILURE;
        }

        $credentialName = $this->promptForRequiredString('Credential name');

        if ($credentialName === null) {
            return self::FAILURE;
        }

        $scopesInput = $this->ask(
            'Scopes (comma separated, press enter for full access)'
        );

        $scopes = $this->normalizeScopes($scopesInput);

        if ($scopes === []) {
            $this->comment('No scopes provided; granting full access token roles.');
            $scopes = self::DEFAULT_SCOPES;
        }

        $expiresAtInput = $this->ask('Expiration (ISO 8601, leave blank for none)');
        $expiresAt = $this->parseExpiration($expiresAtInput);

        if ($this->hasNonEmptyString($expiresAtInput) && $expiresAt === null) {
            return self::FAILURE;
        }

        $apiKey = $this->generateKey();

        $credential = new ApiCredential;
        $credential->fill([
            'id' => (string) Str::uuid(),
            'project_id' => $project->id,
            'environment_id' => $environment->id,
            'name' => $credentialName,
            'scopes' => $scopes,
            'key_hash' => ApiCredentialHasher::make($apiKey),
            'is_active' => true,
            'expires_at' => $expiresAt,
        ]);

        $credential->save();

        $this->newLine();
        $this->info('API key created successfully.');
        $this->newLine();

        $this->table(
            ['Attribute', 'Value'],
            [
                ['Credential ID', $credential->id],
                ['Project', $project->key],
                ['Environment', $environment->key],
                ['Name', $credentialName],
                ['Scopes', implode(', ', $scopes)],
                ['Expires At', $expiresAt?->toIso8601String() ?? 'None'],
            ]
        );

        $this->newLine();
        $this->warn('Store this API key securely:');
        $this->line($apiKey);

        return self::SUCCESS;
    }

    private function promptForRequiredString(string $prompt): ?string
    {
        $response = $this->ask($prompt);

        if (! is_string($response)) {
            $this->error('A value is required.');

            return null;
        }

        $value = trim($response);

        if ($value === '') {
            $this->error('A value is required.');

            return null;
        }

        return $value;
    }

    /**
     * @return array<int, string>
     */
    private function normalizeScopes(?string $scopes): array
    {
        if (! is_string($scopes)) {
            return [];
        }

        $segments = array_map(
            static fn (string $scope): string => trim($scope),
            explode(',', $scopes)
        );

        $filtered = array_values(array_filter($segments, static fn (string $scope): bool => $scope !== ''));

        return array_values(array_unique($filtered));
    }

    private function parseExpiration(mixed $input): ?CarbonImmutable
    {
        if (! is_string($input)) {
            return null;
        }

        $value = trim($input);

        if ($value === '') {
            return null;
        }

        try {
            $expiresAt = CarbonImmutable::parse($value);
        } catch (Exception) {
            $this->error('The expiration value must be a valid date or datetime.');

            return null;
        }

        $now = CarbonImmutable::instance($this->clock->now());

        if ($expiresAt->lessThanOrEqualTo($now)) {
            $this->error('The expiration time must be in the future.');

            return null;
        }

        return $expiresAt;
    }

    private function generateKey(): string
    {
        return Str::random(48);
    }

    private function hasNonEmptyString(mixed $value): bool
    {
        if (! is_string($value)) {
            return false;
        }

        return trim($value) !== '';
    }
}
