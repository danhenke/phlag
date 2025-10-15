<?php

declare(strict_types=1);

namespace Phlag\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;
use Phlag\Http\Requests\StoreProjectRequest;
use Phlag\Http\Requests\UpdateProjectRequest;
use Phlag\Http\Resources\ProjectResource;
use Phlag\Models\Project;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ProjectController extends Controller
{
    private const SECURITY = [['BearerAuth' => []]];

    /**
     * List projects with pagination support.
     */
    #[OA\Get(
        path: '/v1/projects',
        operationId: 'listProjects',
        summary: 'List projects',
        tags: ['Projects'],
        parameters: [
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
                description: 'Paginated list of projects.',
                content: new OA\JsonContent(ref: '#/components/schemas/ProjectCollection')
            ),
            new OA\Response(
                response: 401,
                description: 'Authentication is required.',
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
        ],
        security: self::SECURITY
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = (int) $request->integer('per_page', 15);
        $perPage = max(1, min($perPage, 100));

        /** @var Builder<Project> $query */
        $query = Project::query()->with('environments');

        $projects = $query
            ->orderBy('name')
            ->paginate($perPage);

        return ProjectResource::collection($projects);
    }

    /**
     * Store a newly created project.
     */
    #[OA\Post(
        path: '/v1/projects',
        operationId: 'createProject',
        summary: 'Create a project',
        tags: ['Projects'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/ProjectCreateRequest')
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Project created.',
                headers: [
                    new OA\Header(
                        header: 'Location',
                        description: 'URI of the created project.',
                        schema: new OA\Schema(type: 'string', format: 'uri')
                    ),
                ],
                content: new OA\JsonContent(ref: '#/components/schemas/ProjectResponse')
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
                response: 422,
                description: 'Validation failed.',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(
                response: 500,
                description: 'Unexpected server error.',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ],
        security: self::SECURITY
    )]
    public function store(StoreProjectRequest $request): JsonResponse
    {
        /** @var array<string, mixed> $data */
        $data = $request->validated();

        $project = Project::query()->create([
            'id' => (string) Str::uuid(),
            'key' => $data['key'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'metadata' => $data['metadata'] ?? null,
        ]);

        $project->load('environments');

        $resource = new ProjectResource($project);

        return $resource->response()
            ->setStatusCode(HttpResponse::HTTP_CREATED)
            ->header('Location', route('projects.show', ['project' => $project]));
    }

    /**
     * Display the specified project.
     */
    #[OA\Get(
        path: '/v1/projects/{project}',
        operationId: 'getProject',
        summary: 'Fetch a project',
        tags: ['Projects'],
        parameters: [
            new OA\PathParameter(
                name: 'project',
                description: 'Project key.',
                required: true,
                schema: new OA\Schema(type: 'string')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Project details.',
                content: new OA\JsonContent(ref: '#/components/schemas/ProjectResponse')
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
                response: 500,
                description: 'Unexpected server error.',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ],
        security: self::SECURITY
    )]
    public function show(Project $project): ProjectResource
    {
        $project->load('environments');

        return new ProjectResource($project);
    }

    /**
     * Update the specified project.
     */
    #[OA\Patch(
        path: '/v1/projects/{project}',
        operationId: 'updateProject',
        summary: 'Update a project',
        tags: ['Projects'],
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
            content: new OA\JsonContent(ref: '#/components/schemas/ProjectUpdateRequest')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Project updated.',
                content: new OA\JsonContent(ref: '#/components/schemas/ProjectResponse')
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
        ],
        security: self::SECURITY
    )]
    #[OA\Put(
        path: '/v1/projects/{project}',
        operationId: 'replaceProject',
        summary: 'Replace a project',
        tags: ['Projects'],
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
            content: new OA\JsonContent(ref: '#/components/schemas/ProjectUpdateRequest')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Project updated.',
                content: new OA\JsonContent(ref: '#/components/schemas/ProjectResponse')
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
        ],
        security: self::SECURITY
    )]
    public function update(UpdateProjectRequest $request, Project $project): ProjectResource
    {
        /** @var array<string, mixed> $data */
        $data = $request->validated();

        $project->fill($data);
        $project->save();

        /** @var Project $project */
        $project = $project->fresh(['environments']);

        return new ProjectResource($project);
    }

    /**
     * Remove the specified project.
     */
    #[OA\Delete(
        path: '/v1/projects/{project}',
        operationId: 'deleteProject',
        summary: 'Delete a project',
        tags: ['Projects'],
        parameters: [
            new OA\PathParameter(
                name: 'project',
                description: 'Project key.',
                required: true,
                schema: new OA\Schema(type: 'string')
            ),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Project deleted.'),
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
                response: 500,
                description: 'Unexpected server error.',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ],
        security: self::SECURITY
    )]
    public function destroy(Project $project): Response
    {
        $project->delete();

        return response()->noContent();
    }
}
