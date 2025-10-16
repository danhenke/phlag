<?php

declare(strict_types=1);

namespace Phlag\Auth\Rbac;

use function array_filter;
use function array_key_exists;
use function array_map;
use function array_unique;
use function array_values;
use function config;
use function in_array;
use function is_array;
use function is_string;
use function trim;

final class RoleRegistry
{
    /**
     * @param  array<string, mixed>  $roles
     * @param  array<string, string>  $permissionCatalog
     * @param  array<int, string>  $defaultRoles
     */
    public function __construct(
        private readonly array $roles,
        private readonly array $permissionCatalog,
        private readonly array $defaultRoles,
    ) {}

    public static function make(): self
    {
        /** @var array<string, mixed> $roleConfig */
        $roleConfig = config('rbac.roles', []);

        /** @var array<string, string> $permissionConfig */
        $permissionConfig = config('rbac.permissions', []);

        /** @var array<int, string> $defaultRoles */
        $defaultRoles = config('rbac.defaults.token_roles', []);

        return new self($roleConfig, $permissionConfig, $defaultRoles);
    }

    /**
     * @return array<string, mixed>
     */
    public function definitions(): array
    {
        return $this->roles;
    }

    public function hasRole(string $role): bool
    {
        return array_key_exists($role, $this->roles);
    }

    /**
     * @return array<int, string>
     */
    public function permissionsForRole(string $role): array
    {
        if (! $this->hasRole($role)) {
            return [];
        }

        $definition = $this->roles[$role];

        if (! is_array($definition)) {
            return [];
        }

        $permissions = $definition['permissions'] ?? [];

        if (! is_array($permissions)) {
            return [];
        }

        $normalized = array_values(array_filter(array_map(
            static function ($permission): ?string {
                if (! is_string($permission)) {
                    return null;
                }

                $value = trim($permission);

                return $value === '' ? null : $value;
            },
            $permissions
        )));

        return array_values(array_unique($normalized));
    }

    /**
     * @param  array<int, string>  $roles
     * @return array<int, string>
     */
    public function permissionsForRoles(array $roles): array
    {
        $granted = [];

        foreach ($roles as $role) {
            $granted = array_merge($granted, $this->permissionsForRole($role));
        }

        return array_values(array_unique($granted));
    }

    /**
     * @return array<int, string>
     */
    public function knownPermissions(): array
    {
        /** @var array<int, string> $keys */
        $keys = array_keys($this->permissionCatalog);

        return $keys;
    }

    /**
     * @param  array<int, string>  $permissions
     */
    public function containsPermission(array $permissions, string $permission): bool
    {
        return in_array($permission, $permissions, true);
    }

    /**
     * @param  array<int, mixed>  $roles
     * @return array<int, string>
     */
    public function normalizeRoles(array $roles): array
    {
        $normalized = array_values(array_unique(array_filter(array_map(
            static function ($role): ?string {
                if (! is_string($role)) {
                    return null;
                }

                $value = trim($role);

                return $value === '' ? null : $value;
            },
            $roles
        ))));

        return array_values(array_filter(
            $normalized,
            fn (string $role): bool => $this->hasRole($role)
        ));
    }

    /**
     * @param  array<int, string>  $roles
     * @return array<int, string>
     */
    public function resolveRoles(array $roles): array
    {
        $normalized = $this->normalizeRoles($roles);

        if ($normalized !== []) {
            return $normalized;
        }

        return $this->defaultRoles();
    }

    /**
     * @return array<int, string>
     */
    public function defaultRoles(): array
    {
        if ($this->defaultRoles === []) {
            return $this->normalizeRoles(['project.maintainer']);
        }

        $normalized = $this->normalizeRoles($this->defaultRoles);

        if ($normalized !== []) {
            return $normalized;
        }

        return $this->normalizeRoles(['project.maintainer']);
    }
}
