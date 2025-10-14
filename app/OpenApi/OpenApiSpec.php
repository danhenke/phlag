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
final class OpenApiSpec {}
