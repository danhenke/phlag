<?php

declare(strict_types=1);

namespace Phlag\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Phlag\Auth\Jwt\TokenClaims;
use Phlag\Http\Responses\ApiErrorResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

use function app;
use function array_filter;
use function array_map;
use function array_values;
use function explode;
use function trim;

final class AuthorizeTokenScopes
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string ...$scopeGroups): Response
    {
        try {
            /** @var TokenClaims $claims */
            $claims = app(TokenClaims::class);
        } catch (Throwable) {
            return ApiErrorResponse::make(
                'unauthenticated',
                'A bearer token is required to access this resource.',
                Response::HTTP_UNAUTHORIZED
            );
        }

        $requirements = $this->parseRequirements($scopeGroups);

        foreach ($requirements as $requiredScopes) {
            if (! $claims->hasAnyRole($requiredScopes)) {
                return ApiErrorResponse::make(
                    'forbidden',
                    'The provided token is missing a required scope for this operation.',
                    Response::HTTP_FORBIDDEN,
                    ['required_scopes' => $requiredScopes]
                );
            }
        }

        return $next($request);
    }

    /**
     * @param  array<int|string, string>  $scopeGroups
     * @return array<int, array<int, string>>
     */
    private function parseRequirements(array $scopeGroups): array
    {
        $requirements = [];

        foreach ($scopeGroups as $group) {
            $scopes = array_values(array_filter(array_map(
                static fn (string $scope): string => trim($scope),
                explode('|', $group)
            ), static fn (string $scope): bool => $scope !== ''));

            if ($scopes !== []) {
                $requirements[] = $scopes;
            }
        }

        return $requirements;
    }
}
