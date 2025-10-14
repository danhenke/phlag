<?php

declare(strict_types=1);

namespace Phlag\OpenApi\Paths;

use OpenApi\Attributes as OA;

final class StubPaths
{
    #[OA\Post(
        path: '/v1/auth/token',
        operationId: 'issueAuthToken',
        summary: 'Issue an authentication token (stub)',
        tags: ['Authentication'],
        responses: [
            new OA\Response(
                response: 501,
                description: 'Endpoint not implemented yet.',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
    public function token(): void
    {
        // Intentionally empty; route defined via OpenAPI annotations.
    }
}
