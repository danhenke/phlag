<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Permission Catalog
    |--------------------------------------------------------------------------
    |
    | Human-readable descriptions for the permissions enforced throughout the
    | HTTP API and supporting CLI commands. These strings double as the
    | identifiers attached to JWT claims, so avoid renaming them without
    | coordinating a data migration.
    |
    */
    'permissions' => [
        'projects.read' => 'View project metadata and linked environments.',
        'projects.manage' => 'Create, update, and delete projects.',
        'environments.read' => 'List and inspect environments for a project.',
        'environments.manage' => 'Create, update, and delete environments.',
        'flags.read' => 'Inspect flag definitions and metadata.',
        'flags.manage' => 'Create, update, and delete flags.',
        'flags.evaluate' => 'Evaluate flags for users and contexts.',
        'cache.warm' => 'Warm Redis caches for a project environment.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Role Definitions
    |--------------------------------------------------------------------------
    |
    | Roles bundle the above permissions into coarse-grained access profiles.
    | JWTs now encode role identifiers and the application resolves the granted
    | permissions at runtime.
    |
    */
    'roles' => [
        'project.viewer' => [
            'label' => 'Project Viewer',
            'description' => 'Read-only access to project metadata, environments, and flag evaluation.',
            'permissions' => [
                'projects.read',
                'environments.read',
                'flags.read',
                'flags.evaluate',
            ],
        ],
        'environment.operator' => [
            'label' => 'Environment Operator',
            'description' => 'Operate environments without mutating configuration; pairs with cache warming duties.',
            'permissions' => [
                'environments.read',
                'flags.read',
                'flags.evaluate',
                'cache.warm',
            ],
        ],
        'project.maintainer' => [
            'label' => 'Project Maintainer',
            'description' => 'Full control of project configuration including environments, flags, and cache operations.',
            'permissions' => [
                'projects.read',
                'projects.manage',
                'environments.read',
                'environments.manage',
                'flags.read',
                'flags.manage',
                'flags.evaluate',
                'cache.warm',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Role Assignment
    |--------------------------------------------------------------------------
    |
    | API credentials created without explicit roles receive this list. These
    | values are merged into the JWT during token exchange.
    |
    */
    'defaults' => [
        'token_roles' => ['project.maintainer'],
    ],
];
