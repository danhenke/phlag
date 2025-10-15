<?php

declare(strict_types=1);

namespace Phlag\Commands\Cache;

use LaravelZero\Framework\Commands\Command;
use Phlag\Evaluations\Cache\FlagCacheRepository;
use Phlag\Evaluations\Cache\FlagSignatureHasher;
use Phlag\Evaluations\Cache\FlagSnapshotFactory;
use Phlag\Evaluations\EvaluationContext;
use Phlag\Evaluations\EvaluationResult;
use Phlag\Evaluations\FlagEvaluator;
use Phlag\Models\Environment;
use Phlag\Models\Evaluation;
use Phlag\Models\Flag;
use Phlag\Models\Project;

final class WarmCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'cache:warm
                            {project : The project key to warm}
                            {environment : The environment key to warm}';

    /**
     * @var string
     */
    protected $description = 'Hydrate Redis snapshot and evaluation caches for a project environment.';

    public function __construct(
        private readonly FlagCacheRepository $cacheRepository,
        private readonly FlagSnapshotFactory $snapshotFactory,
        private readonly FlagEvaluator $evaluator,
        private readonly FlagSignatureHasher $signatureHasher
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $projectArgument = $this->argument('project');
        $environmentArgument = $this->argument('environment');

        if (! is_string($projectArgument) || $projectArgument === '') {
            $this->error('The project key must be a non-empty string.');

            return self::FAILURE;
        }

        if (! is_string($environmentArgument) || $environmentArgument === '') {
            $this->error('The environment key must be a non-empty string.');

            return self::FAILURE;
        }

        $projectKey = $projectArgument;
        $environmentKey = $environmentArgument;

        $project = Project::query()->where('key', $projectKey)->first();

        if ($project === null) {
            $this->error(sprintf('Project [%s] not found.', $projectKey));

            return self::FAILURE;
        }

        $environment = Environment::query()
            ->where('project_id', $project->id)
            ->where('key', $environmentKey)
            ->first();

        if ($environment === null) {
            $this->error(sprintf(
                'Environment [%s] not found for project [%s].',
                $environmentKey,
                $projectKey
            ));

            return self::FAILURE;
        }

        $flags = Flag::query()
            ->where('project_id', $project->id)
            ->get();

        if ($flags->isEmpty()) {
            $this->warn(sprintf(
                'No flags found for project [%s]; snapshot cache warmed without flag entries.',
                $projectKey
            ));
        }

        $snapshot = $this->snapshotFactory->make($project, $environment, $flags);
        $this->cacheRepository->storeSnapshot($project->key, $environment->key, $snapshot);

        $evaluationQuery = Evaluation::query()
            ->where('project_id', $project->id)
            ->where('environment_id', $environment->id)
            ->orderBy('evaluated_at');

        $flagsById = $flags->keyBy('id');
        $warmedEvaluations = 0;

        /** @var \Illuminate\Support\LazyCollection<int, Evaluation> $evaluations */
        $evaluations = $evaluationQuery->lazy();

        foreach ($evaluations as $evaluation) {
            if (! is_string($evaluation->flag_id) || $evaluation->flag_id === '') {
                continue;
            }

            $flag = $flagsById->get($evaluation->flag_id);

            if (! $flag instanceof Flag) {
                continue;
            }

            /** @var array<string, mixed>|null $requestContext */
            $requestContext = $evaluation->request_context;
            $attributes = $this->normalizeAttributes($requestContext);

            /** @var string|null $userIdentifier */
            $userIdentifier = $evaluation->user_identifier;

            $context = new EvaluationContext(
                project: $project,
                environment: $environment,
                flag: $flag,
                userIdentifier: $userIdentifier,
                attributes: $attributes
            );

            $result = $this->evaluator->evaluate($context);
            $cachePayload = $this->cachePayload($result);
            $flagSignature = $this->signatureHasher->hash($flag);

            $this->cacheRepository->storeEvaluation(
                $project->key,
                $environment->key,
                $flag->key,
                $context->userIdentifier,
                $attributes,
                $cachePayload,
                $flagSignature
            );

            $warmedEvaluations++;
        }

        $this->info(sprintf(
            'Snapshot cache populated for project [%s] environment [%s].',
            $projectKey,
            $environmentKey
        ));

        $this->info(sprintf(
            'Seeded %d evaluation cache entr%s based on historical evaluations.',
            $warmedEvaluations,
            $warmedEvaluations === 1 ? 'y' : 'ies'
        ));

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>|null  $context
     * @return array<string, array<int, string>>
     */
    private function normalizeAttributes(?array $context): array
    {
        if (! is_array($context) || $context === []) {
            return [];
        }

        $attributes = [];

        foreach ($context as $key => $value) {
            if (! is_string($key) || $key === '') {
                continue;
            }

            if (is_array($value)) {
                $values = array_values(array_filter(
                    array_map(
                        static fn ($item): ?string => is_scalar($item) ? (string) $item : null,
                        $value
                    ),
                    static fn (?string $item): bool => $item !== null && $item !== ''
                ));

                if ($values !== []) {
                    $attributes[$key] = $values;
                }

                continue;
            }

            if (is_scalar($value)) {
                $stringValue = (string) $value;

                if ($stringValue !== '') {
                    $attributes[$key] = [$stringValue];
                }
            }
        }

        return $attributes;
    }

    /**
     * @return array{
     *     variant: string|null,
     *     reason: string,
     *     rollout: int,
     *     payload?: array<string, mixed>,
     *     bucket?: int
     * }
     */
    private function cachePayload(EvaluationResult $result): array
    {
        $payload = [
            'variant' => $result->variant,
            'reason' => $result->reason,
            'rollout' => $result->rollout,
        ];

        if ($result->payload !== null) {
            $payload['payload'] = $result->payload;
        }

        if ($result->bucket !== null) {
            $payload['bucket'] = $result->bucket;
        }

        return $payload;
    }
}
