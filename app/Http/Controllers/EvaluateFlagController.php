<?php

declare(strict_types=1);

namespace Phlag\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Phlag\Evaluations\Cache\FlagCacheRepository;
use Phlag\Evaluations\Cache\FlagSignatureHasher;
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
        private readonly FlagSnapshotFactory $snapshotFactory,
        private readonly FlagSignatureHasher $signatureHasher
    ) {}

    public function __invoke(EvaluateFlagRequest $request): JsonResponse
    {
        $projectKey = $request->projectKey();
        $environmentKey = $request->environmentKey();
        $flagKey = $request->flagKey();

        $userIdentifier = $request->userIdentifier();
        $attributes = $request->contextAttributes();

        $snapshot = $this->cacheRepository->getSnapshot($projectKey, $environmentKey);

        $project = null;
        $environment = null;
        $flag = null;
        $flagSnapshotData = null;
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
                } else {
                    $flagSnapshotData = $flagData;
                    $flag = $this->snapshotFactory->hydrateFlag($flagData);
                }
            } else {
                $shouldRefreshSnapshot = true;
            }
        }

        if (
            ! $shouldRefreshSnapshot
            && $project instanceof Project
            && $environment instanceof Environment
            && $flag instanceof Flag
        ) {
            $flagSignature = $this->signatureHasher->hash($flag);

            $context = new EvaluationContext(
                project: $project,
                environment: $environment,
                flag: $flag,
                userIdentifier: $userIdentifier,
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

                return $this->respond($context, $result);
            }
        }

        if (! $project instanceof Project) {
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

        if (! $environment instanceof Environment) {
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

        if (! $shouldRefreshSnapshot && $flagSnapshotData !== null) {
            $snapshotEnabled = (bool) ($flagSnapshotData['is_enabled'] ?? false);
            $snapshotVariants = $flagSnapshotData['variants'] ?? null;
            $snapshotRules = $flagSnapshotData['rules'] ?? null;
            $snapshotUpdatedAt = is_string($flagSnapshotData['updated_at'] ?? null)
                ? $flagSnapshotData['updated_at']
                : null;

            if (
                $snapshotEnabled !== (bool) $flagRecord->is_enabled
                || $snapshotVariants !== $flagRecord->variants
                || $snapshotRules !== $flagRecord->rules
                || ($snapshotUpdatedAt !== $flagRecord->updated_at?->toISOString())
            ) {
                $shouldRefreshSnapshot = true;
            }
        }

        $flag = $flagRecord;

        if ($shouldRefreshSnapshot) {
            $flags = Flag::query()
                ->where('project_id', $project->id)
                ->get();

            $snapshotPayload = $this->snapshotFactory->make($project, $environment, $flags);
            $this->cacheRepository->storeSnapshot($project->key, $environment->key, $snapshotPayload);
        }

        $flagSignature = $this->signatureHasher->hash($flag);

        $context = new EvaluationContext(
            project: $project,
            environment: $environment,
            flag: $flag,
            userIdentifier: $userIdentifier,
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

        return $this->respond($context, $result);
    }

    private function respond(EvaluationContext $context, EvaluationResult $result): JsonResponse
    {
        $evaluation = Evaluation::query()->create([
            'id' => (string) Str::uuid(),
            'project_id' => $context->project->id,
            'environment_id' => $context->environment->id,
            'flag_id' => $context->flag->id,
            'flag_key' => $context->flag->key,
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
}
