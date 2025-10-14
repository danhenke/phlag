<?php

declare(strict_types=1);

namespace Phlag\Http\Responses;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

final class ApiStub
{
    /**
     * Build a standardized not implemented JSON response for placeholder endpoints.
     */
    public static function notImplemented(?string $label = null): JsonResponse
    {
        $request = request();

        $endpoint = sprintf(
            '%s %s',
            strtoupper($request->getMethod()),
            $request->getPathInfo() ?: '/'
        );

        $label ??= $endpoint;

        return ApiErrorResponse::make(
            'not_implemented',
            sprintf('%s is not available yet.', $label),
            HttpResponse::HTTP_NOT_IMPLEMENTED,
            context: [
                'endpoint' => $endpoint,
            ],
            detail: sprintf('%s remains under development.', $label)
        );
    }
}
