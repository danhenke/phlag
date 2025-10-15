<?php

declare(strict_types=1);

namespace Phlag\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\File;
use OpenApi\Attributes as OA;
use Phlag\Http\Responses\ApiErrorResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

final class PostmanCollectionController extends Controller
{
    #[OA\Get(
        path: '/postman/FeatureFlagService.postman_collection.json',
        operationId: 'getPostmanCollection',
        summary: 'Retrieve the Postman collection JSON.',
        tags: ['Documentation'],
        responses: [
            new OA\Response(
                response: HttpResponse::HTTP_OK,
                description: 'Postman collection JSON.',
                content: new OA\JsonContent(type: 'object')
            ),
            new OA\Response(response: HttpResponse::HTTP_NOT_FOUND, description: 'Collection artifact is not available.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: HttpResponse::HTTP_INTERNAL_SERVER_ERROR, description: 'Collection artifact could not be parsed.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function __invoke(): JsonResponse
    {
        $collectionPath = base_path('postman/FeatureFlagService.postman_collection.json');

        if (! File::exists($collectionPath)) {
            return ApiErrorResponse::make(
                'resource_not_found',
                'Postman collection has not been generated yet.',
                HttpResponse::HTTP_NOT_FOUND,
                context: ['path' => 'postman/FeatureFlagService.postman_collection.json']
            );
        }

        /** @var array<array-key, mixed>|null $collection */
        $collection = json_decode(File::get($collectionPath), true);

        if (! is_array($collection)) {
            return ApiErrorResponse::make(
                'server_error',
                'Failed to read the Postman collection artifact.',
                HttpResponse::HTTP_INTERNAL_SERVER_ERROR,
                context: ['path' => 'postman/FeatureFlagService.postman_collection.json'],
                detail: json_last_error_msg()
            );
        }

        $response = response()->json($collection, HttpResponse::HTTP_OK, [
            'Content-Disposition' => 'inline; filename="FeatureFlagService.postman_collection.json"',
        ]);

        $response->headers->set(
            'Cache-Control',
            'no-store, no-cache, must-revalidate, max-age=0'
        );

        $lastModified = File::lastModified($collectionPath);
        $response->headers->set(
            'Last-Modified',
            gmdate('D, d M Y H:i:s', $lastModified).' GMT'
        );

        return $response;
    }
}
