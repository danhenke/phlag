<?php

declare(strict_types=1);

namespace Phlag\OpenApi\Paths;

use OpenApi\Attributes as OA;

final class EvaluationPaths
{
    #[OA\Get(
        path: '/v1/evaluate',
        operationId: 'evaluateFlag',
        summary: 'Evaluate a feature flag',
        description: 'Determines the variant to serve for a project/environment/flag combination using rollout rules and default fallbacks.',
        tags: ['Flags'],
        parameters: [
            new OA\QueryParameter(
                name: 'project',
                description: 'Project key that owns the flag.',
                required: true,
                schema: new OA\Schema(type: 'string')
            ),
            new OA\QueryParameter(
                name: 'env',
                description: 'Environment key within the project.',
                required: true,
                schema: new OA\Schema(type: 'string')
            ),
            new OA\QueryParameter(
                name: 'flag',
                description: 'Flag key to evaluate.',
                required: true,
                schema: new OA\Schema(type: 'string')
            ),
            new OA\QueryParameter(
                name: 'user_id',
                description: 'Optional user identifier used for deterministic rollout bucketing.',
                required: false,
                schema: new OA\Schema(type: 'string')
            ),
            new OA\QueryParameter(
                name: 'context',
                description: 'Additional evaluation context (pass as repeated query parameters such as `?country=US&segment=beta-testers`).',
                required: false,
                style: 'deepObject',
                explode: true,
                schema: new OA\Schema(
                    type: 'object',
                    additionalProperties: new OA\AdditionalProperties(type: 'string', nullable: true)
                )
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Evaluation completed successfully.',
                content: new OA\JsonContent(ref: '#/components/schemas/FlagEvaluationResponse')
            ),
            new OA\Response(
                response: 404,
                description: 'Project, environment, or flag not found.',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(
                response: 422,
                description: 'Validation failed for the evaluation request.',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(
                response: 500,
                description: 'Unexpected server error.',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ],
        security: [['BearerAuth' => []]]
    )]
    public function evaluate(): void {}
}
