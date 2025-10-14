<?php

declare(strict_types=1);

namespace Phlag\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Phlag\Evaluations\EvaluationContext;
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
    public function __construct(private readonly FlagEvaluator $evaluator) {}

    public function __invoke(EvaluateFlagRequest $request): JsonResponse
    {
        $project = Project::query()->where('key', $request->projectKey())->first();

        if ($project === null) {
            return ApiErrorResponse::make(
                'resource_not_found',
                'Project not found.',
                HttpResponse::HTTP_NOT_FOUND,
                context: ['project' => $request->projectKey()]
            );
        }

        $environment = Environment::query()
            ->where('project_id', $project->id)
            ->where('key', $request->environmentKey())
            ->first();

        if ($environment === null) {
            return ApiErrorResponse::make(
                'resource_not_found',
                'Environment not found.',
                HttpResponse::HTTP_NOT_FOUND,
                context: [
                    'project' => $project->key,
                    'environment' => $request->environmentKey(),
                ]
            );
        }

        $flag = Flag::query()
            ->where('project_id', $project->id)
            ->where('key', $request->flagKey())
            ->first();

        if ($flag === null) {
            return ApiErrorResponse::make(
                'resource_not_found',
                'Flag not found.',
                HttpResponse::HTTP_NOT_FOUND,
                context: [
                    'project' => $project->key,
                    'flag' => $request->flagKey(),
                ]
            );
        }

        $context = new EvaluationContext(
            project: $project,
            environment: $environment,
            flag: $flag,
            userIdentifier: $request->userIdentifier(),
            attributes: $request->contextAttributes(),
        );

        $result = $this->evaluator->evaluate($context);

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
}
