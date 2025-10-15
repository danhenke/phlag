<?php

declare(strict_types=1);

namespace Phlag\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class LogHttpRequests
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = microtime(true);
        $response = null;

        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            $this->logRequest($request, null, microtime(true) - $startedAt, $exception);

            throw $exception;
        }

        $this->logRequest($request, $response, microtime(true) - $startedAt);

        return $response;
    }

    private function logRequest(Request $request, ?Response $response, float $durationSeconds, ?Throwable $exception = null): void
    {
        $status = $response?->getStatusCode() ?? 500;
        $message = $exception === null ? 'HTTP request handled' : 'HTTP request failed';

        $context = [
            'method' => $request->getMethod(),
            'uri' => $request->getRequestUri(),
            'status' => $status,
            'duration_ms' => round($durationSeconds * 1000, 2),
            'user_agent' => $request->userAgent() ?? 'unknown',
            'ip' => $request->getClientIp(),
        ];

        if ($exception !== null) {
            $context['exception'] = $exception;

            Log::error($message, $context);

            return;
        }

        Log::info($message, $context);
    }
}
