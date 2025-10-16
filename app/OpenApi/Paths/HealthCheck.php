<?php

declare(strict_types=1);

namespace Phlag\OpenApi\Paths;

use OpenApi\Attributes as OA;

#[OA\PathItem(path: '/')]
final class HealthCheck
{
    #[OA\Get(
        path: '/',
        operationId: 'getHealthCheck',
        summary: 'Retrieve service health status.',
        tags: ['System'],
        security: [],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Service is available.',
                content: new OA\JsonContent(
                    type: 'object',
                    required: ['service', 'status', 'timestamp'],
                    properties: [
                        new OA\Property(
                            property: 'service',
                            type: 'string',
                            description: 'Service identifier.'
                        ),
                        new OA\Property(
                            property: 'status',
                            type: 'string',
                            enum: ['ok'],
                            description: 'Service availability status.'
                        ),
                        new OA\Property(
                            property: 'timestamp',
                            type: 'string',
                            format: 'date-time',
                            description: 'ISO-8601 timestamp when the health check was evaluated.'
                        ),
                    ],
                    description: 'Health check payload.'
                )
            ),
            new OA\Response(
                response: 500,
                description: 'Unexpected server error.',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
    public function __invoke(): void
    {
        // Specification-only placeholder.
    }
}
