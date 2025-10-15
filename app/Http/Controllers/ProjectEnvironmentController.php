<?php

declare(strict_types=1);

namespace Phlag\Http\Controllers;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;
use Phlag\Http\Requests\StoreEnvironmentRequest;
use Phlag\Http\Requests\UpdateEnvironmentRequest;
use Phlag\Http\Resources\EnvironmentResource;
use Phlag\Models\Environment;
use Phlag\Models\Project;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ProjectEnvironmentController extends Controller
{
    /**
     * List environments for the given project.
     */
    #[OA\Get(
        path: '/v1/projects/{project}/environments',
        operationId: 'listProjectEnvironments',
        summary: 'List environments for a project',
        tags: ['Environments'],
        parameters: [
            new OA\PathParameter(
                name: 'project',
                description: 'Project key.',
                required: true,
                schema: new OA\Schema(type: 'string')
            ),
            new OA\QueryParameter(
                name: 'page',
                description: 'Page number to retrieve.',
                required: false,
                schema: new OA\Schema(type: 'integer', minimum: 1)
            ),
            new OA\QueryParameter(
                name: 'per_page',
                description: 'Number of results per page (1-100).',
                required: false,
                schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated list of environments.',
                content: new OA\JsonContent(ref: '#/components/schemas/EnvironmentCollection')
            ),
            new OA\Response(
                response: 401,
                description: 'Authentication is required.',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(
                response: 404,
                description: 'Project not found.',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(
                response: 429,
                description: 'Too many requests.',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(
                response: 500,
                description: 'Unexpected server error.',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
    public function index(Request $request, Project $project): AnonymousResourceCollection
    {
        $perPage = (int) $request->integer('per_page', 15);
        $perPage = max(1, min($perPage, 100));

        /** @var HasMany<Environment, Project> $relation */
        $relation = $project->environments();

        $environments = $relation
            ->orderBy('name')
            ->paginate($perPage);

        return EnvironmentResource::collection($environments);
    }

    /**
     * Store a newly created environment.
     */
    #[OA\Post(
        path: '/v1/projects/{project}/environments',
        operationId: 'createProjectEnvironment',
        summary: 'Create an environment for a project',
        tags: ['Environments'],
        parameters: [
            new OA\PathParameter(
                name: 'project',
                description: 'Project key.',
                required: true,
                schema: new OA\Schema(type: 'string')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/EnvironmentCreateRequest')
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Environment created.',
                headers: [
                    new OA\Header(
                        header: 'Location',
                        description: 'URI of the created environment.',
                        schema: new OA\Schema(type: 'string', format: 'uri')
                    ),
                ],
                content: new OA\JsonContent(ref: '#/components/schemas/EnvironmentResponse')
            ),
            new OA\Response(
                response: 400,
                description: 'Request body contained malformed JSON.',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(
                response: 401,
                description: 'Authentication is required.',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(
                response: 404,
                description: 'Project not found.',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(
                response: 422,
                description: 'Validation failed.',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(
                response: 500,
                description: 'Unexpected server error.',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
    public function store(StoreEnvironmentRequest $request, Project $project): JsonResponse
    {
        /** @var array<string, mixed> $data */
        $data = $request->validated();

        $environment = $project->environments()->create([
            'id' => (string) Str::uuid(),
            'key' => $data['key'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_default' => (bool) ($data['is_default'] ?? false),
            'metadata' => $data['metadata'] ?? null,
        ]);

        $this->syncDefaultEnvironment($project, $environment);

        $environment->refresh();

        $resource = new EnvironmentResource($environment);

        return $resource->response()
            ->setStatusCode(HttpResponse::HTTP_CREATED)
            ->header('Location', route('projects.environments.show', [
                'project' => $project,
                'environment' => $environment,
            ]));
    }

    /**
     * Display the specified environment.
     */
    #[OA\Get(
        path: '/v1/projects/{project}/environments/{environment}',
        operationId: 'getProjectEnvironment',
        summary: 'Fetch an environment',
        tags: ['Environments'],
        parameters: [
            new OA\PathParameter(
                name: 'project',
                description: 'Project key.',
                required: true,
                schema: new OA\Schema(type: 'string')
            ),
            new OA\PathParameter(
                name: 'environment',
                description: 'Environment key.',
                required: true,
                schema: new OA\Schema(type: 'string')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Environment details.',
                content: new OA\JsonContent(ref: '#/components/schemas/EnvironmentResponse')
            ),
            new OA\Response(
                response: 401,
                description: 'Authentication is required.',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(
                response: 404,
                description: 'Environment or project not found.',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(
                response: 500,
                description: 'Unexpected server error.',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
    public function show(Project $project, Environment $environment): EnvironmentResource
    {
        return new EnvironmentResource($environment);
    }

    /**
     * Update the specified environment.
     */
    #[OA\Patch(
        path: '/v1/projects/{project}/environments/{environment}',
        operationId: 'updateProjectEnvironment',
        summary: 'Update an environment',
        tags: ['Environments'],
        parameters: [
            new OA\PathParameter(
                name: 'project',
                description: 'Project key.',
                required: true,
                schema: new OA\Schema(type: 'string')
            ),
            new OA\PathParameter(
                name: 'environment',
                description: 'Environment key.',
                required: true,
                schema: new OA\Schema(type: 'string')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/EnvironmentUpdateRequest')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Environment updated.',
                content: new OA\JsonContent(ref: '#/components/schemas/EnvironmentResponse')
            ),
            new OA\Response(
                response: 400,
                description: 'Request body contained malformed JSON.',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(
                response: 401,
                description: 'Authentication is required.',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(
                response: 404,
                description: 'Environment or project not found.',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(
                response: 422,
                description: 'Validation failed.',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(
                response: 500,
                description: 'Unexpected server error.',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
    #[OA\Put(
        path: '/v1/projects/{project}/environments/{environment}',
        operationId: 'replaceProjectEnvironment',
        summary: 'Replace an environment',
        tags: ['Environments'],
        parameters: [
            new OA\PathParameter(
                name: 'project',
                description: 'Project key.',
                required: true,
                schema: new OA\Schema(type: 'string')
            ),
            new OA\PathParameter(
                name: 'environment',
                description: 'Environment key.',
                required: true,
                schema: new OA\Schema(type: 'string')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/EnvironmentUpdateRequest')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Environment updated.',
                content: new OA\JsonContent(ref: '#/components/schemas/EnvironmentResponse')
            ),
            new OA\Response(
                response: 400,
                description: 'Request body contained malformed JSON.',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(
                response: 401,
                description: 'Authentication is required.',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(
                response: 404,
                description: 'Environment or project not found.',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(
                response: 422,
                description: 'Validation failed.',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(
                response: 500,
                description: 'Unexpected server error.',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
    public function update(
        UpdateEnvironmentRequest $request,
        Project $project,
        Environment $environment
    ): EnvironmentResource {
        /** @var array<string, mixed> $data */
        $data = $request->validated();

        $environment->fill($data);
        $environment->save();

        if (array_key_exists('is_default', $data)) {
            $environment->setAttribute('is_default', (bool) $data['is_default']);
        }

        $this->syncDefaultEnvironment($project, $environment);

        $environment->refresh();

        return new EnvironmentResource($environment);
    }

    /**
     * Remove the specified environment.
     */
    #[OA\Delete(
        path: '/v1/projects/{project}/environments/{environment}',
        operationId: 'deleteProjectEnvironment',
        summary: 'Delete an environment',
        tags: ['Environments'],
        parameters: [
            new OA\PathParameter(
                name: 'project',
                description: 'Project key.',
                required: true,
                schema: new OA\Schema(type: 'string')
            ),
            new OA\PathParameter(
                name: 'environment',
                description: 'Environment key.',
                required: true,
                schema: new OA\Schema(type: 'string')
            ),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Environment deleted.'),
            new OA\Response(
                response: 401,
                description: 'Authentication is required.',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(
                response: 404,
                description: 'Environment or project not found.',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(
                response: 500,
                description: 'Unexpected server error.',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
    public function destroy(Project $project, Environment $environment): Response
    {
        $environment->delete();

        return response()->noContent();
    }

    private function syncDefaultEnvironment(Project $project, Environment $environment): void
    {
        if (! (bool) $environment->getAttribute('is_default')) {
            return;
        }

        $project->environments()
            ->where('id', '!=', $environment->id)
            ->update(['is_default' => false]);
    }
}
