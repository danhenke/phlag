<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Phlag\Auth\Rbac\RoleRegistry;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('api_credentials', function (Blueprint $table): void {
            $table->jsonb('roles')->nullable()->after('name');
            $table->jsonb('permissions')->nullable()->after('roles');
        });

        $registry = RoleRegistry::make();

        $credentials = DB::table('api_credentials')
            ->select('id', 'scopes')
            ->get();

        foreach ($credentials as $credential) {
            /** @var mixed $scopes */
            $scopes = $credential->scopes;
            $normalizedPermissions = $this->normalizedPermissions($scopes);
            $roles = $this->mapScopesToRoles($registry, $normalizedPermissions);

            DB::table('api_credentials')
                ->where('id', $credential->id)
                ->update([
                    'roles' => $roles,
                    'permissions' => $normalizedPermissions,
                ]);
        }

        Schema::table('api_credentials', function (Blueprint $table): void {
            $table->dropColumn('scopes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('api_credentials', function (Blueprint $table): void {
            $table->jsonb('scopes')->nullable()->after('name');
        });

        $registry = RoleRegistry::make();

        $credentials = DB::table('api_credentials')
            ->select('id', 'roles', 'permissions')
            ->get();

        foreach ($credentials as $credential) {
            /** @var mixed $roles */
            $roles = $credential->roles;
            /** @var mixed $permissions */
            $permissions = $credential->permissions;
            $scopes = $this->mapRolesToScopes($registry, $roles, $permissions);

            DB::table('api_credentials')
                ->where('id', $credential->id)
                ->update(['scopes' => $scopes]);
        }

        Schema::table('api_credentials', function (Blueprint $table): void {
            $table->dropColumn(['permissions', 'roles']);
        });
    }

    /**
     * @return array<int, string>
     */
    private function mapScopesToRoles(RoleRegistry $registry, mixed $scopes): array
    {
        $normalizedScopes = $this->normalizedPermissions($scopes);

        if ($normalizedScopes === []) {
            return $registry->defaultRoles();
        }

        $roles = [];
        $definitions = $registry->definitions();

        foreach ($definitions as $role => $definition) {
            if (! is_array($definition)) {
                continue;
            }

            $rolePermissions = $this->normalizedPermissions($definition['permissions'] ?? []);

            if ($rolePermissions === []) {
                continue;
            }

            $isSubset = empty(array_diff($rolePermissions, $normalizedScopes));

            if ($isSubset) {
                $roles[] = $role;
            }
        }

        if ($roles === []) {
            return [];
        }

        return $registry->normalizeRoles($roles);
    }

    /**
     * @return array<int, string>
     */
    private function mapRolesToScopes(RoleRegistry $registry, mixed $roles, mixed $permissions): array
    {
        $roleList = [];

        if (is_array($roles)) {
            $roleList = array_values(array_filter(
                $roles,
                static fn ($role): bool => is_string($role) && $role !== ''
            ));
        }

        /** @var array<int, string> $normalizedRoles */
        $normalizedRoles = $registry->normalizeRoles($roleList);
        $explicitPermissions = $this->normalizedPermissions($permissions);

        if ($normalizedRoles === [] && $explicitPermissions === []) {
            $normalizedRoles = $registry->defaultRoles();
        }

        $rolePermissions = $registry->permissionsForRoles($normalizedRoles);

        return array_values(array_unique(array_merge($rolePermissions, $explicitPermissions)));
    }

    /**
     * @param  array<int, string>|mixed  $permissions
     * @return array<int, string>
     */
    private function normalizedPermissions(mixed $permissions): array
    {
        if (! is_array($permissions)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            static function ($permission): ?string {
                if (! is_string($permission)) {
                    return null;
                }

                $value = trim($permission);

                return $value === '' ? null : $value;
            },
            $permissions
        ))));
    }
};
