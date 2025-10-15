<?php

declare(strict_types=1);

namespace Phlag\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use OpenApi\Attributes as OA;
use Phlag\Http\Responses\ApiErrorResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

final class OpenApiController extends Controller
{
    #[OA\Get(
        path: '/v1/openapi.json',
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
        ],
        security: []
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

    #[OA\Get(
        path: '/docs',
        operationId: 'getSwaggerUi',
        summary: 'Render Swagger UI backed by the generated OpenAPI spec.',
        tags: ['Documentation'],
        responses: [
            new OA\Response(
                response: HttpResponse::HTTP_OK,
                description: 'Swagger UI HTML response.',
                content: new OA\MediaType(mediaType: 'text/html')
            ),
        ],
        security: []
    )]
    public function ui(): Response
    {
        $specUrl = htmlspecialchars(
            url('/v1/openapi.json'),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );

        $tagOrder = [
            'System',
            'Documentation',
            'Authentication',
            'Projects',
            'Environments',
            'Flags',
        ];

        $pathOrder = [
            'System' => [
                '/',
            ],
            'Documentation' => [
                '/docs',
                '/v1/openapi.json',
                '/v1/postman.json',
            ],
            'Authentication' => [
                '/v1/auth/token',
            ],
            'Projects' => [
                '/v1/projects',
                '/v1/projects/{project}',
            ],
            'Environments' => [
                '/v1/projects/{project}/environments',
                '/v1/projects/{project}/environments/{environment}',
            ],
            'Flags' => [
                '/v1/projects/{project}/flags',
                '/v1/projects/{project}/flags/{flag}',
                '/v1/evaluate',
            ],
        ];

        $methodOrder = ['get', 'post', 'put', 'patch', 'delete', 'options', 'head'];

        $tagOrderJson = json_encode($tagOrder, JSON_THROW_ON_ERROR);
        $pathOrderJson = json_encode($pathOrder, JSON_THROW_ON_ERROR);
        $methodOrderJson = json_encode($methodOrder, JSON_THROW_ON_ERROR);

        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Phlag API Docs</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui.css">
</head>
<body>
<div id="swagger-ui"></div>
<script src="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
<script src="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui-standalone-preset.js"></script>
<script>
    window.addEventListener('load', function () {
        const tagOrder = {$tagOrderJson};
        const pathOrder = {$pathOrderJson};
        const methodOrder = {$methodOrderJson};

        function compareTags(tagA, tagB) {
            const indexA = tagOrder.indexOf(tagA);
            const indexB = tagOrder.indexOf(tagB);

            if (indexA === -1 && indexB === -1) {
                return tagA.localeCompare(tagB);
            }

            if (indexA === -1) {
                return 1;
            }

            if (indexB === -1) {
                return -1;
            }

            return indexA - indexB;
        }

        function comparePaths(tag, pathA, pathB) {
            const order = pathOrder[tag] || [];
            const indexA = order.indexOf(pathA);
            const indexB = order.indexOf(pathB);

            if (indexA === -1 && indexB === -1) {
                return pathA.localeCompare(pathB);
            }

            if (indexA === -1) {
                return 1;
            }

            if (indexB === -1) {
                return -1;
            }

            return indexA - indexB;
        }

        function compareMethods(methodA, methodB) {
            const indexA = methodOrder.indexOf(methodA);
            const indexB = methodOrder.indexOf(methodB);

            if (indexA === -1 && indexB === -1) {
                if (methodA === methodB) {
                    return 0;
                }

                return methodA.localeCompare(methodB);
            }

            if (indexA === -1) {
                return 1;
            }

            if (indexB === -1) {
                return -1;
            }

            return indexA - indexB;
        }

        window.ui = SwaggerUIBundle({
            dom_id: '#swagger-ui',
            url: '{$specUrl}',
            layout: 'BaseLayout',
            docExpansion: 'list',
            defaultModelsExpandDepth: -1,
            presets: [
                SwaggerUIBundle.presets.apis,
                SwaggerUIStandalonePreset
            ],
            tagsSorter: function (a, b) {
                return compareTags(a, b);
            },
            operationsSorter: function (opA, opB) {
                const tagA = (opA.get('tags') || [])[0] || '';
                const tagB = (opB.get('tags') || [])[0] || '';

                if (tagA !== tagB) {
                    return compareTags(tagA, tagB);
                }

                const pathA = opA.get('path');
                const pathB = opB.get('path');

                const pathComparison = comparePaths(tagA, pathA, pathB);

                if (pathComparison !== 0) {
                    return pathComparison;
                }

                const methodA = (opA.get('method') || '').toLowerCase();
                const methodB = (opB.get('method') || '').toLowerCase();

                const methodComparison = compareMethods(methodA, methodB);

                if (methodComparison !== 0) {
                    return methodComparison;
                }

                return pathA.localeCompare(pathB) || methodA.localeCompare(methodB);
            },
        });
    });
</script>
</body>
</html>
HTML;

        return response(
            $html,
            HttpResponse::HTTP_OK,
            [
                'Content-Type' => 'text/html; charset=UTF-8',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            ]
        );
    }
}
