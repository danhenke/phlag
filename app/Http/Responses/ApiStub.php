<?php

declare(strict_types=1);

namespace Phlag\Http\Responses;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

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

        return response()->json([
            'endpoint' => $endpoint,
            'error' => 'not_implemented',
            'message' => sprintf('%s is not available yet.', $label),
        ], Response::HTTP_NOT_IMPLEMENTED);
    }
}
