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
        });

        $registry = RoleRegistry::make();

        $credentials = DB::table('api_credentials')
            ->select('id', 'scopes')
            ->get();

        foreach ($credentials as $credential) {
            /** @var mixed $scopes */
            $scopes = $credential->scopes;
            $roles = $this->mapScopesToRoles($registry, $scopes);

            DB::table('api_credentials')
                ->where('id', $credential->id)
                ->update(['roles' => $roles]);
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
            ->select('id', 'roles')
            ->get();

        foreach ($credentials as $credential) {
            /** @var mixed $roles */
            $roles = $credential->roles;
            $scopes = $this->mapRolesToScopes($registry, $roles);

            DB::table('api_credentials')
                ->where('id', $credential->id)
                ->update(['scopes' => $scopes]);
        }

        Schema::table('api_credentials', function (Blueprint $table): void {
            $table->dropColumn('roles');
        });
    }

    /**
     * @return array<int, string>
     */
    private function mapScopesToRoles(RoleRegistry $registry, mixed $scopes): array
    {
        if (! is_array($scopes)) {
            return $registry->defaultRoles();
        }

        $normalizedScopes = array_values(array_unique(array_filter(array_map(
            static function ($scope): ?string {
                if (! is_string($scope)) {
                    return null;
                }

                $value = trim($scope);

                return $value === '' ? null : $value;
            },
            $scopes
        ))));

        if ($normalizedScopes === []) {
            return $registry->defaultRoles();
        }

        $manageScopes = [
            'projects.manage',
            'environments.manage',
            'flags.manage',
        ];

        foreach ($manageScopes as $manageScope) {
            if (in_array($manageScope, $normalizedScopes, true)) {
                return $registry->normalizeRoles(['project.maintainer']);
            }
        }

        $viewerScopes = [
            'projects.read',
            'environments.read',
            'flags.read',
            'flags.evaluate',
        ];

        $roles = [];

        if ($this->containsAny($normalizedScopes, $viewerScopes)) {
            $roles[] = 'project.viewer';
        }

        if (in_array('cache.warm', $normalizedScopes, true)) {
            $roles[] = 'environment.operator';
        }

        if ($roles === []) {
            return $registry->defaultRoles();
        }

        return $registry->normalizeRoles($roles);
    }

    /**
     * @return array<int, string>
     */
    private function mapRolesToScopes(RoleRegistry $registry, mixed $roles): array
    {
        $roleList = [];

        if (is_array($roles)) {
            $roleList = array_values(array_filter(
                $roles,
                static fn ($role): bool => is_string($role) && $role !== ''
            ));
        }

        /** @var array<int, string> $normalizedRoles */
        $normalizedRoles = $registry->resolveRoles($roleList);

        return $registry->permissionsForRoles($normalizedRoles);
    }

    /**
     * @param  array<int, string>  $haystack
     * @param  array<int, string>  $needles
     */
    private function containsAny(array $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (in_array($needle, $haystack, true)) {
                return true;
            }
        }

        return false;
    }
};
