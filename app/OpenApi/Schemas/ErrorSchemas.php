<?php

declare(strict_types=1);

namespace Phlag\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ErrorViolation',
    type: 'object',
    required: ['field', 'message'],
    properties: [
        new OA\Property(
            property: 'field',
            type: 'string',
            description: 'Field path that failed validation.'
        ),
        new OA\Property(
            property: 'message',
            type: 'string',
            description: 'Human-readable description of the validation error.'
        ),
    ],
    description: 'Details of a single validation violation.'
)]
#[OA\Schema(
    schema: 'ErrorEnvelope',
    type: 'object',
    required: ['code', 'message', 'status'],
    properties: [
        new OA\Property(
            property: 'code',
            type: 'string',
            description: 'Machine-readable error identifier.',
            enum: [
                'bad_request',
                'conflict',
                'forbidden',
                'http_error',
                'method_not_allowed',
                'not_implemented',
                'rate_limited',
                'resource_not_found',
                'server_error',
                'unauthorized',
                'validation_failed',
            ]
        ),
        new OA\Property(
            property: 'message',
            type: 'string',
            description: 'Concise, human-readable explanation of the error.'
        ),
        new OA\Property(
            property: 'status',
            type: 'integer',
            format: 'int32',
            description: 'HTTP status code associated with the error.'
        ),
        new OA\Property(
            property: 'detail',
            type: 'string',
            nullable: true,
            description: 'Optional detail for debugging when debug mode is enabled.'
        ),
        new OA\Property(
            property: 'context',
            type: 'object',
            nullable: true,
            description: 'Optional key-value pairs providing additional context for the error.'
        ),
        new OA\Property(
            property: 'violations',
            type: 'array',
            nullable: true,
            items: new OA\Items(ref: '#/components/schemas/ErrorViolation'),
            description: 'List of validation violations when the error represents validation failures.'
        ),
    ],
    description: 'Standardized error payload returned by the Phlag API.'
)]
#[OA\Schema(
    schema: 'ErrorResponse',
    type: 'object',
    required: ['error'],
    properties: [
        new OA\Property(
            property: 'error',
            ref: '#/components/schemas/ErrorEnvelope',
            description: 'Standardized error envelope.'
        ),
    ],
    description: 'Envelope for all error responses returned by the Phlag API.'
)]
final class ErrorSchemas {}
