<?php

declare(strict_types=1);

namespace Phlag\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\File;
use OpenApi\Attributes as OA;
use Phlag\Http\Responses\ApiErrorResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

final class OpenApiController extends Controller
{
    #[OA\Get(
        path: '/v1/docs/openapi.json',
        operationId: 'getOpenApiDocument',
        summary: 'Retrieve the OpenAPI specification JSON.',
        tags: ['Documentation'],
        responses: [
            new OA\Response(
                response: HttpResponse::HTTP_OK,
                description: 'OpenAPI document as JSON.',
                content: new OA\JsonContent(type: 'object')
            ),
            new OA\Response(
                response: HttpResponse::HTTP_NOT_FOUND,
                description: 'Specification artifact is not available.',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(
                response: HttpResponse::HTTP_INTERNAL_SERVER_ERROR,
                description: 'Specification artifact could not be parsed.',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
    public function show(): JsonResponse
    {
        $artifactPath = base_path('docs/openapi.json');

        if (! File::exists($artifactPath)) {
            return ApiErrorResponse::make(
                'resource_not_found',
                'OpenAPI specification has not been generated yet.',
                HttpResponse::HTTP_NOT_FOUND,
                context: ['path' => 'docs/openapi.json']
            );
        }

        /** @var array<array-key, mixed>|null $document */
        $document = json_decode(File::get($artifactPath), true);

        if (! is_array($document)) {
            return ApiErrorResponse::make(
                'server_error',
                'Failed to read the OpenAPI specification artifact.',
                HttpResponse::HTTP_INTERNAL_SERVER_ERROR,
                context: ['path' => 'docs/openapi.json'],
                detail: json_last_error_msg()
            );
        }

        $response = response()->json($document, HttpResponse::HTTP_OK, [
            'Content-Disposition' => 'inline; filename="openapi.json"',
        ]);

        $response->headers->set(
            'Cache-Control',
            'no-store, no-cache, must-revalidate, max-age=0'
        );

        $lastModified = File::lastModified($artifactPath);
        $response->headers->set(
            'Last-Modified',
            gmdate('D, d M Y H:i:s', $lastModified).' GMT'
        );

        return $response;
    }
}
