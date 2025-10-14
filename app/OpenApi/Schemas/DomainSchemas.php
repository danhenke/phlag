<?php

declare(strict_types=1);

namespace Phlag\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Project',
    required: ['id', 'key', 'name', 'created_at', 'updated_at', 'environments'],
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid', example: 'c0a8012a-7bb2-45cb-9e7a-17a4cda9e90a'),
        new OA\Property(property: 'key', type: 'string', example: 'checkout-service'),
        new OA\Property(property: 'name', type: 'string', example: 'Checkout Service'),
        new OA\Property(property: 'description', type: 'string', nullable: true),
        new OA\Property(
            property: 'metadata',
            type: 'object',
            nullable: true,
            additionalProperties: new OA\AdditionalProperties(type: 'string')
        ),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
        new OA\Property(
            property: 'environments',
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/Environment')
        ),
    ],
    description: 'Project representation returned by the API.'
)]
#[OA\Schema(
    schema: 'ProjectResponse',
    required: ['data'],
    properties: [
        new OA\Property(property: 'data', ref: '#/components/schemas/Project'),
    ],
    description: 'Single project response envelope.'
)]
#[OA\Schema(
    schema: 'PaginationLinks',
    type: 'object',
    additionalProperties: new OA\AdditionalProperties(type: 'string', nullable: true),
    description: 'Pagination links keyed by relation name.'
)]
#[OA\Schema(
    schema: 'PaginationMeta',
    type: 'object',
    properties: [
        new OA\Property(property: 'current_page', type: 'integer', minimum: 1),
        new OA\Property(property: 'per_page', type: 'integer', minimum: 1),
        new OA\Property(property: 'total', type: 'integer', minimum: 0),
        new OA\Property(property: 'last_page', type: 'integer', minimum: 1, nullable: true),
    ],
    additionalProperties: new OA\AdditionalProperties,
    description: 'Pagination metadata returned alongside resource collections.'
)]
#[OA\Schema(
    schema: 'ProjectCollection',
    required: ['data', 'links', 'meta'],
    properties: [
        new OA\Property(
            property: 'data',
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/Project')
        ),
        new OA\Property(property: 'links', ref: '#/components/schemas/PaginationLinks'),
        new OA\Property(property: 'meta', ref: '#/components/schemas/PaginationMeta'),
    ],
    description: 'Paginated project collection response.'
)]
#[OA\Schema(
    schema: 'Environment',
    required: ['id', 'key', 'name', 'is_default', 'created_at', 'updated_at'],
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'key', type: 'string'),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'description', type: 'string', nullable: true),
        new OA\Property(property: 'is_default', type: 'boolean'),
        new OA\Property(
            property: 'metadata',
            type: 'object',
            nullable: true,
            additionalProperties: new OA\AdditionalProperties(type: 'string')
        ),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ],
    description: 'Environment representation.'
)]
#[OA\Schema(
    schema: 'EnvironmentResponse',
    required: ['data'],
    properties: [
        new OA\Property(property: 'data', ref: '#/components/schemas/Environment'),
    ],
    description: 'Single environment response envelope.'
)]
#[OA\Schema(
    schema: 'EnvironmentCollection',
    required: ['data', 'links', 'meta'],
    properties: [
        new OA\Property(
            property: 'data',
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/Environment')
        ),
        new OA\Property(property: 'links', ref: '#/components/schemas/PaginationLinks'),
        new OA\Property(property: 'meta', ref: '#/components/schemas/PaginationMeta'),
    ],
    description: 'Paginated environment collection response.'
)]
#[OA\Schema(
    schema: 'FlagVariant',
    required: ['key'],
    properties: [
        new OA\Property(property: 'key', type: 'string', example: 'variant-a'),
        new OA\Property(property: 'weight', type: 'integer', minimum: 0, maximum: 100, nullable: true),
        new OA\Property(
            property: 'payload',
            type: 'object',
            nullable: true,
            additionalProperties: new OA\AdditionalProperties
        ),
    ],
    description: 'Flag variant configuration.'
)]
#[OA\Schema(
    schema: 'FlagRule',
    required: ['match', 'variant'],
    properties: [
        new OA\Property(
            property: 'match',
            type: 'object',
            additionalProperties: new OA\AdditionalProperties(
                type: 'array',
                items: new OA\Items(type: 'string')
            )
        ),
        new OA\Property(property: 'variant', type: 'string'),
        new OA\Property(property: 'rollout', type: 'integer', nullable: true, minimum: 0, maximum: 100),
    ],
    description: 'Flag evaluation rule definition.'
)]
#[OA\Schema(
    schema: 'Flag',
    required: ['id', 'project_id', 'key', 'name', 'is_enabled', 'variants', 'created_at', 'updated_at'],
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'project_id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'key', type: 'string'),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'description', type: 'string', nullable: true),
        new OA\Property(property: 'is_enabled', type: 'boolean'),
        new OA\Property(
            property: 'variants',
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/FlagVariant')
        ),
        new OA\Property(
            property: 'rules',
            type: 'array',
            nullable: true,
            items: new OA\Items(ref: '#/components/schemas/FlagRule')
        ),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ],
    description: 'Flag representation.'
)]
#[OA\Schema(
    schema: 'FlagResponse',
    required: ['data'],
    properties: [
        new OA\Property(property: 'data', ref: '#/components/schemas/Flag'),
    ],
    description: 'Single flag response envelope.'
)]
#[OA\Schema(
    schema: 'FlagCollection',
    required: ['data', 'links', 'meta'],
    properties: [
        new OA\Property(
            property: 'data',
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/Flag')
        ),
        new OA\Property(property: 'links', ref: '#/components/schemas/PaginationLinks'),
        new OA\Property(property: 'meta', ref: '#/components/schemas/PaginationMeta'),
    ],
    description: 'Paginated flag collection response.'
)]
#[OA\Schema(
    schema: 'ProjectCreateRequest',
    required: ['key', 'name'],
    properties: [
        new OA\Property(property: 'key', type: 'string', maxLength: 64, pattern: '^[a-z0-9][a-z0-9-]*$'),
        new OA\Property(property: 'name', type: 'string', maxLength: 255),
        new OA\Property(property: 'description', type: 'string', nullable: true),
        new OA\Property(
            property: 'metadata',
            type: 'object',
            nullable: true,
            additionalProperties: new OA\AdditionalProperties(type: 'string')
        ),
    ],
    description: 'Payload for creating a project.'
)]
#[OA\Schema(
    schema: 'ProjectUpdateRequest',
    properties: [
        new OA\Property(property: 'key', type: 'string', maxLength: 64, pattern: '^[a-z0-9][a-z0-9-]*$'),
        new OA\Property(property: 'name', type: 'string', maxLength: 255),
        new OA\Property(property: 'description', type: 'string', nullable: true),
        new OA\Property(
            property: 'metadata',
            type: 'object',
            nullable: true,
            additionalProperties: new OA\AdditionalProperties(type: 'string')
        ),
    ],
    description: 'Payload for updating a project.'
)]
#[OA\Schema(
    schema: 'EnvironmentCreateRequest',
    required: ['key', 'name'],
    properties: [
        new OA\Property(property: 'key', type: 'string', maxLength: 64, pattern: '^[a-z0-9][a-z0-9-]*$'),
        new OA\Property(property: 'name', type: 'string', maxLength: 255),
        new OA\Property(property: 'description', type: 'string', nullable: true),
        new OA\Property(property: 'is_default', type: 'boolean', nullable: true),
        new OA\Property(
            property: 'metadata',
            type: 'object',
            nullable: true,
            additionalProperties: new OA\AdditionalProperties(type: 'string')
        ),
    ],
    description: 'Payload for creating an environment.'
)]
#[OA\Schema(
    schema: 'EnvironmentUpdateRequest',
    properties: [
        new OA\Property(property: 'key', type: 'string', maxLength: 64, pattern: '^[a-z0-9][a-z0-9-]*$'),
        new OA\Property(property: 'name', type: 'string', maxLength: 255),
        new OA\Property(property: 'description', type: 'string', nullable: true),
        new OA\Property(property: 'is_default', type: 'boolean', nullable: true),
        new OA\Property(
            property: 'metadata',
            type: 'object',
            nullable: true,
            additionalProperties: new OA\AdditionalProperties(type: 'string')
        ),
    ],
    description: 'Payload for updating an environment.'
)]
#[OA\Schema(
    schema: 'FlagCreateRequest',
    required: ['key', 'name', 'variants'],
    properties: [
        new OA\Property(property: 'key', type: 'string', maxLength: 64, pattern: '^[a-z0-9][a-z0-9-]*$'),
        new OA\Property(property: 'name', type: 'string', maxLength: 255),
        new OA\Property(property: 'description', type: 'string', nullable: true),
        new OA\Property(property: 'is_enabled', type: 'boolean', nullable: true),
        new OA\Property(
            property: 'variants',
            type: 'array',
            minItems: 1,
            items: new OA\Items(ref: '#/components/schemas/FlagVariant')
        ),
        new OA\Property(
            property: 'rules',
            type: 'array',
            nullable: true,
            items: new OA\Items(ref: '#/components/schemas/FlagRule')
        ),
    ],
    description: 'Payload for creating a flag.'
)]
#[OA\Schema(
    schema: 'FlagUpdateRequest',
    properties: [
        new OA\Property(property: 'key', type: 'string', maxLength: 64, pattern: '^[a-z0-9][a-z0-9-]*$'),
        new OA\Property(property: 'name', type: 'string', maxLength: 255),
        new OA\Property(property: 'description', type: 'string', nullable: true),
        new OA\Property(property: 'is_enabled', type: 'boolean', nullable: true),
        new OA\Property(
            property: 'variants',
            type: 'array',
            minItems: 1,
            nullable: true,
            items: new OA\Items(ref: '#/components/schemas/FlagVariant')
        ),
        new OA\Property(
            property: 'rules',
            type: 'array',
            nullable: true,
            items: new OA\Items(ref: '#/components/schemas/FlagRule')
        ),
    ],
    description: 'Payload for updating a flag.'
)]
final class DomainSchemas {}
