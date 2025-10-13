<?php

declare(strict_types=1);

namespace Phlag\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Phlag\Http\Requests\StoreProjectRequest;
use Phlag\Http\Requests\UpdateProjectRequest;
use Phlag\Http\Resources\ProjectResource;
use Phlag\Models\Project;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ProjectController extends Controller
{
    /**
     * List projects with pagination support.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = (int) $request->integer('per_page', 15);
        $perPage = max(1, min($perPage, 100));

        $projects = Project::query()
            ->with(['environments' => fn ($query) => $query->orderBy('name')])
            ->orderBy('name')
            ->paginate($perPage);

        return ProjectResource::collection($projects);
    }

    /**
     * Store a newly created project.
     */
    public function store(StoreProjectRequest $request): JsonResponse
    {
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
    public function show(Project $project): ProjectResource
    {
        $project->load(['environments' => fn ($query) => $query->orderBy('name')]);

        return new ProjectResource($project);
    }

    /**
     * Update the specified project.
     */
    public function update(UpdateProjectRequest $request, Project $project): ProjectResource
    {
        $data = $request->validated();

        $project->fill($data);
        $project->save();

        $project->load(['environments' => fn ($query) => $query->orderBy('name')]);

        return new ProjectResource($project);
    }

    /**
     * Remove the specified project.
     */
    public function destroy(Project $project): Response
    {
        $project->delete();

        return response()->noContent();
    }
}
