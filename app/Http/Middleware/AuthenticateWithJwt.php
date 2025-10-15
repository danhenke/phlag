<?php

declare(strict_types=1);

namespace Phlag\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Phlag\Auth\Jwt\JwtTokenVerifier;
use Phlag\Auth\Jwt\TokenClaims;
use Phlag\Http\Responses\ApiErrorResponse;
use Symfony\Component\HttpFoundation\Response;

use function app;
use function preg_replace;
use function str_starts_with;
use function trim;

final class AuthenticateWithJwt
{
    public function __construct(
        private readonly JwtTokenVerifier $verifier,
    ) {}

    /**
     * @param  Closure(Request): \Symfony\Component\HttpFoundation\Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->extractBearerToken($request);

        if ($token === null) {
            return ApiErrorResponse::make(
                'unauthenticated',
                'A bearer token is required to access this resource.',
                Response::HTTP_UNAUTHORIZED
            );
        }

        $result = $this->verifier->verify($token);

        if (! $result->isValid()) {
            return ApiErrorResponse::make(
                $result->code() ?? 'unauthenticated',
                $result->message() ?? 'The provided token is invalid.',
                Response::HTTP_UNAUTHORIZED,
                $result->context()
            );
        }

        $claims = $result->claims();
        $this->storeClaimsOnRequest($request, $claims);

        return $next($request);
    }

    private function extractBearerToken(Request $request): ?string
    {
        $authorization = $request->headers->get('Authorization');

        if (! is_string($authorization) || trim($authorization) === '') {
            return null;
        }

        if (str_starts_with($authorization, 'Bearer ') || str_starts_with($authorization, 'bearer ')) {
            $token = trim((string) preg_replace('/^Bearer\s+/i', '', $authorization));

            return $token === '' ? null : $token;
        }

        return null;
    }

    private function storeClaimsOnRequest(Request $request, TokenClaims $claims): void
    {
        $request->attributes->set('jwt.claims', $claims);
        $request->setUserResolver(static fn () => $claims);
        app()->instance(TokenClaims::class, $claims);
    }
}
