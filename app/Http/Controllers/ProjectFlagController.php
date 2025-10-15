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
use Phlag\Http\Requests\StoreFlagRequest;
use Phlag\Http\Requests\UpdateFlagRequest;
use Phlag\Http\Resources\FlagResource;
use Phlag\Models\Flag;
use Phlag\Models\Project;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ProjectFlagController extends Controller
{
    private const SECURITY = [['BearerAuth' => []]];

    /**
     * List flags for the given project.
     */
    #[OA\Get(
        path: '/v1/projects/{project}/flags',
        operationId: 'listProjectFlags',
        summary: 'List flags for a project',
        tags: ['Flags'],
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
                description: 'Paginated list of flags.',
                content: new OA\JsonContent(ref: '#/components/schemas/FlagCollection')
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
        ],
        security: self::SECURITY
    )]
    public function index(Request $request, Project $project): AnonymousResourceCollection
    {
        $perPage = (int) $request->integer('per_page', 15);
        $perPage = max(1, min($perPage, 100));

        /** @var HasMany<Flag, Project> $relation */
        $relation = $project->flags();

        $flags = $relation
            ->orderBy('name')
            ->paginate($perPage);

        return FlagResource::collection($flags);
    }

    /**
     * Store a newly created flag.
     */
    #[OA\Post(
        path: '/v1/projects/{project}/flags',
        operationId: 'createProjectFlag',
        summary: 'Create a flag for a project',
        tags: ['Flags'],
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
            content: new OA\JsonContent(ref: '#/components/schemas/FlagCreateRequest')
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Flag created.',
                headers: [
                    new OA\Header(
                        header: 'Location',
                        description: 'URI of the created flag.',
                        schema: new OA\Schema(type: 'string', format: 'uri')
                    ),
                ],
                content: new OA\JsonContent(ref: '#/components/schemas/FlagResponse')
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
    public function store(StoreFlagRequest $request, Project $project): JsonResponse
    {
        /** @var array<string, mixed> $data */
        $data = $request->validated();

        $flag = $project->flags()->create([
            'id' => (string) Str::uuid(),
            'key' => $data['key'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_enabled' => (bool) ($data['is_enabled'] ?? false),
            'variants' => $data['variants'],
            'rules' => $data['rules'] ?? null,
        ]);

        $flag->refresh();

        $resource = new FlagResource($flag);

        return $resource->response()
            ->setStatusCode(HttpResponse::HTTP_CREATED)
            ->header('Location', route('projects.flags.show', [
                'project' => $project,
                'flag' => $flag,
            ]));
    }

    /**
     * Display the specified flag.
     */
    #[OA\Get(
        path: '/v1/projects/{project}/flags/{flag}',
        operationId: 'getProjectFlag',
        summary: 'Fetch a flag',
        tags: ['Flags'],
        parameters: [
            new OA\PathParameter(
                name: 'project',
                description: 'Project key.',
                required: true,
                schema: new OA\Schema(type: 'string')
            ),
            new OA\PathParameter(
                name: 'flag',
                description: 'Flag key.',
                required: true,
                schema: new OA\Schema(type: 'string')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Flag details.',
                content: new OA\JsonContent(ref: '#/components/schemas/FlagResponse')
            ),
            new OA\Response(
                response: 401,
                description: 'Authentication is required.',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(
                response: 404,
                description: 'Flag or project not found.',
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
    public function show(Project $project, Flag $flag): FlagResource
    {
        return new FlagResource($flag);
    }

    /**
     * Update the specified flag.
     */
    #[OA\Patch(
        path: '/v1/projects/{project}/flags/{flag}',
        operationId: 'updateProjectFlag',
        summary: 'Update a flag',
        tags: ['Flags'],
        parameters: [
            new OA\PathParameter(
                name: 'project',
                description: 'Project key.',
                required: true,
                schema: new OA\Schema(type: 'string')
            ),
            new OA\PathParameter(
                name: 'flag',
                description: 'Flag key.',
                required: true,
                schema: new OA\Schema(type: 'string')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/FlagUpdateRequest')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Flag updated.',
                content: new OA\JsonContent(ref: '#/components/schemas/FlagResponse')
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
                description: 'Flag or project not found.',
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
        path: '/v1/projects/{project}/flags/{flag}',
        operationId: 'replaceProjectFlag',
        summary: 'Replace a flag',
        tags: ['Flags'],
        parameters: [
            new OA\PathParameter(
                name: 'project',
                description: 'Project key.',
                required: true,
                schema: new OA\Schema(type: 'string')
            ),
            new OA\PathParameter(
                name: 'flag',
                description: 'Flag key.',
                required: true,
                schema: new OA\Schema(type: 'string')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/FlagUpdateRequest')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Flag updated.',
                content: new OA\JsonContent(ref: '#/components/schemas/FlagResponse')
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
                description: 'Flag or project not found.',
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
    public function update(UpdateFlagRequest $request, Project $project, Flag $flag): FlagResource
    {
        /** @var array<string, mixed> $data */
        $data = $request->validated();

        $flag->fill($data);

        if (array_key_exists('is_enabled', $data)) {
            $flag->setAttribute('is_enabled', (bool) $data['is_enabled']);
        }

        $flag->save();
        $flag->refresh();

        return new FlagResource($flag);
    }

    /**
     * Remove the specified flag.
     */
    #[OA\Delete(
        path: '/v1/projects/{project}/flags/{flag}',
        operationId: 'deleteProjectFlag',
        summary: 'Delete a flag',
        tags: ['Flags'],
        parameters: [
            new OA\PathParameter(
                name: 'project',
                description: 'Project key.',
                required: true,
                schema: new OA\Schema(type: 'string')
            ),
            new OA\PathParameter(
                name: 'flag',
                description: 'Flag key.',
                required: true,
                schema: new OA\Schema(type: 'string')
            ),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Flag deleted.'),
            new OA\Response(
                response: 401,
                description: 'Authentication is required.',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(
                response: 404,
                description: 'Flag or project not found.',
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
    public function destroy(Project $project, Flag $flag): Response
    {
        $flag->delete();

        return response()->noContent();
    }
}
