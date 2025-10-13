<?php

declare(strict_types=1);

namespace Phlag\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
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
    public function index(Request $request, Project $project): AnonymousResourceCollection
    {
        $perPage = (int) $request->integer('per_page', 15);
        $perPage = max(1, min($perPage, 100));

        $environments = $project->environments()
            ->orderBy('name')
            ->paginate($perPage);

        return EnvironmentResource::collection($environments);
    }

    /**
     * Store a newly created environment.
     */
    public function store(StoreEnvironmentRequest $request, Project $project): JsonResponse
    {
        $data = $request->validated();

        $environment = $project->environments()->create([
            'id' => (string) Str::uuid(),
            'project_id' => $project->id,
            'key' => $data['key'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_default' => (bool) ($data['is_default'] ?? false),
            'metadata' => $data['metadata'] ?? null,
        ]);

        $this->syncDefaultEnvironment($project, $environment);

        $resource = new EnvironmentResource($environment->fresh());

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
    public function show(Project $project, Environment $environment): EnvironmentResource
    {
        return new EnvironmentResource($environment);
    }

    /**
     * Update the specified environment.
     */
    public function update(
        UpdateEnvironmentRequest $request,
        Project $project,
        Environment $environment
    ): EnvironmentResource {
        $data = $request->validated();

        $environment->fill($data);
        $environment->save();

        if (array_key_exists('is_default', $data)) {
            $environment->is_default = (bool) $data['is_default'];
        }

        $this->syncDefaultEnvironment($project, $environment);

        return new EnvironmentResource($environment->fresh());
    }

    /**
     * Remove the specified environment.
     */
    public function destroy(Project $project, Environment $environment): Response
    {
        $environment->delete();

        return response()->noContent();
    }

    private function syncDefaultEnvironment(Project $project, Environment $environment): void
    {
        if (! $environment->is_default) {
            return;
        }

        $project->environments()
            ->where('id', '!=', $environment->id)
            ->update(['is_default' => false]);
    }
}
