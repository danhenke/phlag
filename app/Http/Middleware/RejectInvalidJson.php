<?php

declare(strict_types=1);

namespace Phlag\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use JsonException;
use Phlag\Http\Responses\ApiErrorResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

use function str_contains;

final class RejectInvalidJson
{
    /**
     * Validate incoming JSON payloads and reject malformed bodies.
     *
     * @param  Closure(Request): \Symfony\Component\HttpFoundation\Response  $next
     */
    public function handle(Request $request, Closure $next): HttpResponse
    {
        if (! $this->isJsonRequest($request)) {
            return $next($request);
        }

        $content = $request->getContent();

        if ($content === '' || trim($content) === '') {
            return $next($request);
        }

        try {
            json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            return ApiErrorResponse::make(
                'invalid_json',
                'The request body must be valid JSON.',
                HttpResponse::HTTP_BAD_REQUEST,
                context: ['error' => $exception->getMessage()]
            );
        }

        return $next($request);
    }

    private function isJsonRequest(Request $request): bool
    {
        $contentType = (string) $request->headers->get('Content-Type', '');

        return str_contains($contentType, 'application/json')
            || str_contains($contentType, '+json');
    }
}
