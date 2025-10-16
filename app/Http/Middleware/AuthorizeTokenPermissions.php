<?php

declare(strict_types=1);

namespace Phlag\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Phlag\Auth\Jwt\TokenClaims;
use Phlag\Auth\Rbac\RoleRegistry;
use Phlag\Http\Responses\ApiErrorResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

use function app;
use function array_filter;
use function array_map;
use function array_merge;
use function array_unique;
use function array_values;
use function explode;
use function trim;

final class AuthorizeTokenPermissions
{
    public function __construct(
        private readonly RoleRegistry $roleRegistry
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string ...$permissionGroups): Response
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

        $requirements = $this->parseRequirements($permissionGroups);
        $grantedPermissions = $this->grantedPermissions($claims);

        foreach ($requirements as $requiredPermissions) {
            if (! $this->hasAnyPermission($grantedPermissions, $requiredPermissions)) {
                return ApiErrorResponse::make(
                    'forbidden',
                    'The provided token is missing a required permission for this operation.',
                    Response::HTTP_FORBIDDEN,
                    ['required_permissions' => $requiredPermissions]
                );
            }
        }

        return $next($request);
    }

    /**
     * @param  array<int|string, string>  $permissionGroups
     * @return array<int, array<int, string>>
     */
    private function parseRequirements(array $permissionGroups): array
    {
        $requirements = [];

        foreach ($permissionGroups as $group) {
            $permissions = array_values(array_filter(array_map(
                static fn (string $permission): string => trim($permission),
                explode('|', $group)
            ), static fn (string $permission): bool => $permission !== ''));

            if ($permissions !== []) {
                $requirements[] = $permissions;
            }
        }

        return $requirements;
    }

    /**
     * @param  array<int, string>  $grantedPermissions
     * @param  array<int, string>  $requiredPermissions
     */
    private function hasAnyPermission(array $grantedPermissions, array $requiredPermissions): bool
    {
        foreach ($requiredPermissions as $permission) {
            if ($this->roleRegistry->containsPermission($grantedPermissions, $permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    private function grantedPermissions(TokenClaims $claims): array
    {
        $permissions = $claims->permissions();

        if ($permissions === []) {
            $permissions = $this->roleRegistry->permissionsForRoles($claims->roles());

            return $permissions;
        }

        $rolePermissions = $this->roleRegistry->permissionsForRoles($claims->roles());

        return array_values(array_unique(array_merge($permissions, $rolePermissions)));
    }
}
