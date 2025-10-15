<?php

declare(strict_types=1);

namespace Phlag\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Info(
    title: 'Phlag API',
    version: '1.0.0',
    description: 'API for managing feature flags, environments, and evaluations within Phlag.'
)]
#[OA\Server(
    url: 'http://localhost',
    description: 'Local development server'
)]
#[OA\SecurityScheme(
    securityScheme: 'BearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT',
    description: 'JWT issued via POST /v1/auth/token.'
)]
#[OA\OpenApi(
    security: [
        ['BearerAuth' => []],
    ]
)]
final class OpenApiSpec {}
