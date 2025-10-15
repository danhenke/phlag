<?php

declare(strict_types=1);

namespace Phlag\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Phlag\Evaluations\Cache\FlagCacheRepository;
use Phlag\Evaluations\Cache\FlagSnapshotFactory;
use Phlag\Evaluations\EvaluationContext;
use Phlag\Evaluations\EvaluationResult;
use Phlag\Evaluations\FlagEvaluator;
use Phlag\Http\Requests\EvaluateFlagRequest;
use Phlag\Http\Resources\EvaluationResource;
use Phlag\Http\Responses\ApiErrorResponse;
use Phlag\Models\Environment;
use Phlag\Models\Evaluation;
use Phlag\Models\Flag;
use Phlag\Models\Project;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class EvaluateFlagController extends Controller
{
    public function __construct(
        private readonly FlagEvaluator $evaluator,
        private readonly FlagCacheRepository $cacheRepository,
        private readonly FlagSnapshotFactory $snapshotFactory
    ) {}

    public function __invoke(EvaluateFlagRequest $request): JsonResponse
    {
        $projectKey = $request->projectKey();
        $environmentKey = $request->environmentKey();
        $flagKey = $request->flagKey();

        $attributes = $request->contextAttributes();

        $snapshot = $this->cacheRepository->getSnapshot($projectKey, $environmentKey);

        $project = null;
        $environment = null;
        $flag = null;
        $shouldRefreshSnapshot = $snapshot === null;

        if ($snapshot !== null) {
            /** @var array<string, mixed> $snapshot */
            $projectData = $snapshot['project'] ?? null;
            $environmentData = $snapshot['environment'] ?? null;

            if (is_array($projectData) && is_array($environmentData)) {
                /** @var array<string, mixed> $projectPayload */
                $projectPayload = $projectData;
                /** @var array<string, mixed> $environmentPayload */
                $environmentPayload = $environmentData;

                $project = $this->snapshotFactory->hydrateProject($projectPayload);
                $environment = $this->snapshotFactory->hydrateEnvironment($environmentPayload);

                $flagData = $this->snapshotFactory->findFlag($snapshot, $flagKey);

                if (! is_array($flagData)) {
                    $shouldRefreshSnapshot = true;
                }
            } else {
                $shouldRefreshSnapshot = true;
            }
        }

        if ($project === null) {
            $project = Project::query()->where('key', $projectKey)->first();

            if ($project === null) {
                return ApiErrorResponse::make(
                    'resource_not_found',
                    'Project not found.',
                    HttpResponse::HTTP_NOT_FOUND,
                    context: ['project' => $projectKey]
                );
            }
        }

        if ($environment === null) {
            $environment = Environment::query()
                ->where('project_id', $project->id)
                ->where('key', $environmentKey)
                ->first();

            if ($environment === null) {
                return ApiErrorResponse::make(
                    'resource_not_found',
                    'Environment not found.',
                    HttpResponse::HTTP_NOT_FOUND,
                    context: [
                        'project' => $project->key,
                        'environment' => $environmentKey,
                    ]
                );
            }
        }

        $flagRecord = Flag::query()
            ->where('project_id', $project->id)
            ->where('key', $flagKey)
            ->first();

        if ($flagRecord === null) {
            return ApiErrorResponse::make(
                'resource_not_found',
                'Flag not found.',
                HttpResponse::HTTP_NOT_FOUND,
                context: [
                    'project' => $project->key,
                    'flag' => $flagKey,
                ]
            );
        }

        $flag = $flagRecord;

        if ($shouldRefreshSnapshot) {
            $flags = Flag::query()
                ->where('project_id', $project->id)
                ->get();

            $snapshotPayload = $this->snapshotFactory->make($project, $environment, $flags);
            $this->cacheRepository->storeSnapshot($project->key, $environment->key, $snapshotPayload);
        }

        $flagSignature = $this->flagSignature($flag);

        $context = new EvaluationContext(
            project: $project,
            environment: $environment,
            flag: $flag,
            userIdentifier: $request->userIdentifier(),
            attributes: $attributes,
        );

        $cachedEvaluation = $this->cacheRepository->getEvaluation(
            $project->key,
            $environment->key,
            $flag->key,
            $context->userIdentifier,
            $attributes,
            $flagSignature
        );

        if ($cachedEvaluation !== null) {
            /** @var array{
             *     variant: string|null,
             *     reason: string,
             *     rollout: int,
             *     payload?: array<string, mixed>,
             *     bucket?: int
             * } $cachedEvaluation */
            $result = new EvaluationResult(
                variant: $cachedEvaluation['variant'],
                reason: $cachedEvaluation['reason'],
                rollout: $cachedEvaluation['rollout'],
                payload: $cachedEvaluation['payload'] ?? null,
                bucket: $cachedEvaluation['bucket'] ?? null,
            );
        } else {
            $result = $this->evaluator->evaluate($context);

            $cachePayload = [
                'variant' => $result->variant,
                'reason' => $result->reason,
                'rollout' => $result->rollout,
            ];

            if ($result->payload !== null) {
                $cachePayload['payload'] = $result->payload;
            }

            if ($result->bucket !== null) {
                $cachePayload['bucket'] = $result->bucket;
            }

            $this->cacheRepository->storeEvaluation(
                $project->key,
                $environment->key,
                $flag->key,
                $context->userIdentifier,
                $attributes,
                $cachePayload,
                $flagSignature
            );
        }

        $evaluation = Evaluation::query()->create([
            'id' => (string) Str::uuid(),
            'project_id' => $project->id,
            'environment_id' => $environment->id,
            'flag_id' => $flag->id,
            'flag_key' => $flag->key,
            'variant' => $result->variant,
            'evaluation_reason' => $result->reason,
            'user_identifier' => $context->userIdentifier,
            'request_context' => $context->denormalizedAttributes(),
            'evaluation_payload' => $result->payloadForStorage(),
            'evaluated_at' => now(),
        ]);

        $resource = new EvaluationResource(
            $evaluation,
            $context,
            $result,
            $context->denormalizedAttributes()
        );

        return $resource
            ->response()
            ->setStatusCode(HttpResponse::HTTP_OK)
            ->header('Cache-Control', 'private, no-store, no-cache, must-revalidate, max-age=0')
            ->header('Vary', 'Authorization, Accept-Encoding');
    }

    private function flagSignature(Flag $flag): string
    {
        $payload = [
            'updated_at' => $flag->updated_at?->toISOString(),
            'is_enabled' => (bool) $flag->is_enabled,
            'variants' => $flag->variants,
            'rules' => $flag->rules,
        ];

        try {
            $encoded = json_encode($payload, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return hash('sha1', serialize($payload));
        }

        return hash('sha1', $encoded);
    }
}
