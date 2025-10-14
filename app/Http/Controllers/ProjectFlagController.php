<?php

declare(strict_types=1);

namespace Phlag\Http\Controllers;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Phlag\Http\Requests\StoreFlagRequest;
use Phlag\Http\Requests\UpdateFlagRequest;
use Phlag\Http\Resources\FlagResource;
use Phlag\Models\Flag;
use Phlag\Models\Project;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ProjectFlagController extends Controller
{
    /**
     * List flags for the given project.
     */
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
    public function show(Project $project, Flag $flag): FlagResource
    {
        return new FlagResource($flag);
    }

    /**
     * Update the specified flag.
     */
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
    public function destroy(Project $project, Flag $flag): Response
    {
        $flag->delete();

        return response()->noContent();
    }
}

