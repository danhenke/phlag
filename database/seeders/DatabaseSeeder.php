<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Phlag\Auth\ApiKeys\ApiCredentialHasher;
use Phlag\Auth\Rbac\RoleRegistry;
use Phlag\Models\ApiCredential;
use Phlag\Models\AuditEvent;
use Phlag\Models\Environment;
use Phlag\Models\Evaluation;
use Phlag\Models\Flag;
use Phlag\Models\Project;

use function app;
use function is_string;
use function trim;

class DatabaseSeeder extends Seeder
{
    private const PROJECT_ID = '11111111-1111-4111-9111-111111111111';

    private const PRODUCTION_ENVIRONMENT_ID = '22222222-2222-4222-9222-222222222222';

    private const STAGING_ENVIRONMENT_ID = '33333333-3333-4333-9333-333333333333';

    private const CHECKOUT_FLAG_ID = '44444444-4444-4444-9444-444444444444';

    private const RECOMMENDATIONS_FLAG_ID = '55555555-5555-4555-9555-555555555555';

    private const SEGMENT_EVALUATION_ID = '66666666-6666-4666-9666-666666666666';

    private const DEFAULT_EVALUATION_ID = '77777777-7777-4777-9777-777777777777';

    private const CHECKOUT_AUDIT_ID = '88888888-8888-4888-9888-888888888888';

    private const PROJECT_AUDIT_ID = '99999999-9999-4999-9999-999999999999';

    private const PRODUCTION_API_CREDENTIAL_ID = 'aaaa1111-aaaa-4aaa-9aaa-aaaaaaaaaaaa';

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            $project = $this->seedProject();
            $environments = $this->seedEnvironments($project);
            $this->seedApiCredentials($project, $environments);
            $flags = $this->seedFlags($project);

            $this->seedEvaluations($project, $environments, $flags);
            $this->seedAuditEvents($project, $environments, $flags);
        });
    }

    private function seedProject(): Project
    {
        $project = Project::query()->firstOrNew(['key' => 'demo-project']);

        if (! $project->exists) {
            $project->id = self::PROJECT_ID;
        }

        $project->fill([
            'name' => 'Phlag Demo Project',
            'description' => 'Sample project used to showcase feature flag workflows.',
            'metadata' => [
                'owner' => 'demo@phlag.test',
                'timezone' => 'UTC',
            ],
        ]);

        $project->save();

        return $project->refresh();
    }

    /**
     * @return array<string, Environment>
     */
    private function seedEnvironments(Project $project): array
    {
        $production = Environment::query()->firstOrNew([
            'project_id' => $project->id,
            'key' => 'production',
        ]);

        if (! $production->exists) {
            $production->id = self::PRODUCTION_ENVIRONMENT_ID;
        }

        $production->fill([
            'project_id' => $project->id,
            'name' => 'Production',
            'description' => 'Live customer traffic.',
            'is_default' => true,
            'metadata' => [
                'url' => 'https://phlag.test',
            ],
        ]);

        $production->save();
        $production = $production->refresh();

        $staging = Environment::query()->firstOrNew([
            'project_id' => $project->id,
            'key' => 'staging',
        ]);

        if (! $staging->exists) {
            $staging->id = self::STAGING_ENVIRONMENT_ID;
        }

        $staging->fill([
            'project_id' => $project->id,
            'name' => 'Staging',
            'description' => 'Pre-production validation environment.',
            'is_default' => false,
            'metadata' => [
                'url' => 'https://staging.phlag.test',
            ],
        ]);

        $staging->save();

        $staging = $staging->refresh();

        return [
            'production' => $production,
            'staging' => $staging,
        ];
    }

    /**
     * @return array<string, Flag>
     */
    private function seedFlags(Project $project): array
    {
        $checkout = Flag::query()->firstOrNew([
            'project_id' => $project->id,
            'key' => 'checkout-redesign',
        ]);

        if (! $checkout->exists) {
            $checkout->id = self::CHECKOUT_FLAG_ID;
        }

        $checkout->fill([
            'project_id' => $project->id,
            'name' => 'Checkout Redesign',
            'description' => 'Serve the redesigned checkout funnel.',
            'is_enabled' => true,
            'variants' => [
                ['key' => 'control', 'weight' => 40],
                ['key' => 'variant', 'weight' => 60],
            ],
            'rules' => [
                [
                    'match' => ['country' => ['US', 'CA']],
                    'variant' => 'variant',
                    'rollout' => 75,
                ],
            ],
        ]);

        $checkout->save();
        $checkout = $checkout->refresh();

        $recommendations = Flag::query()->firstOrNew([
            'project_id' => $project->id,
            'key' => 'homepage-recommendations',
        ]);

        if (! $recommendations->exists) {
            $recommendations->id = self::RECOMMENDATIONS_FLAG_ID;
        }

        $recommendations->fill([
            'project_id' => $project->id,
            'name' => 'Homepage Recommendations',
            'description' => 'Expose personalized recommendations on the homepage.',
            'is_enabled' => false,
            'variants' => [
                ['key' => 'off', 'weight' => 100],
            ],
            'rules' => [
                [
                    'match' => ['segment' => ['beta-testers']],
                    'variant' => 'off',
                    'rollout' => 100,
                ],
            ],
        ]);

        $recommendations->save();
        $recommendations = $recommendations->refresh();

        return [
            'checkout' => $checkout,
            'recommendations' => $recommendations,
        ];
    }

    /**
     * @param  array<string, Environment>  $environments
     */
    private function seedApiCredentials(Project $project, array $environments): void
    {
        $production = $environments['production'] ?? null;

        if (! $production instanceof Environment) {
            return;
        }

        $credential = ApiCredential::query()->firstOrNew([
            'id' => self::PRODUCTION_API_CREDENTIAL_ID,
        ]);

        if (! $credential->exists) {
            $credential->id = self::PRODUCTION_API_CREDENTIAL_ID;
        }

        $apiKey = env('PHLAG_DEMO_API_KEY');

        if (! is_string($apiKey) || trim($apiKey) === '') {
            return;
        }

        /** @var RoleRegistry $roleRegistry */
        $roleRegistry = app(RoleRegistry::class);

        $credential->fill([
            'project_id' => $project->id,
            'environment_id' => $production->id,
            'name' => 'Demo Production API credential',
            'roles' => $roleRegistry->defaultRoles(),
            'key_hash' => ApiCredentialHasher::make($apiKey),
            'is_active' => true,
            'expires_at' => null,
        ]);

        $credential->save();
    }

    /**
     * @param  array<string, Environment>  $environments
     * @param  array<string, Flag>  $flags
     */
    private function seedEvaluations(Project $project, array $environments, array $flags): void
    {
        $segmented = Evaluation::query()->firstOrNew(['id' => self::SEGMENT_EVALUATION_ID]);

        if (! $segmented->exists) {
            $segmented->id = self::SEGMENT_EVALUATION_ID;
        }

        $segmented->fill([
            'project_id' => $project->id,
            'environment_id' => $environments['production']->id,
            'flag_id' => $flags['checkout']->id,
            'flag_key' => $flags['checkout']->key,
            'variant' => 'variant',
            'evaluation_reason' => 'matched_segment_rollout',
            'user_identifier' => 'user-123',
            'request_context' => [
                'country' => 'US',
                'segment' => 'beta-testers',
            ],
            'evaluation_payload' => [
                'variant' => 'variant',
                'rollout' => 75,
            ],
            'evaluated_at' => Carbon::now()->subMinutes(5),
        ]);

        $segmented->save();

        $defaulted = Evaluation::query()->firstOrNew(['id' => self::DEFAULT_EVALUATION_ID]);

        if (! $defaulted->exists) {
            $defaulted->id = self::DEFAULT_EVALUATION_ID;
        }

        $defaulted->fill([
            'project_id' => $project->id,
            'environment_id' => $environments['staging']->id,
            'flag_id' => $flags['recommendations']->id,
            'flag_key' => $flags['recommendations']->key,
            'variant' => null,
            'evaluation_reason' => 'fallback_default',
            'user_identifier' => 'user-987',
            'request_context' => [
                'country' => 'GB',
            ],
            'evaluation_payload' => [
                'variant' => 'off',
                'rollout' => 0,
            ],
            'evaluated_at' => Carbon::now()->subMinutes(3),
        ]);

        $defaulted->save();
    }

    /**
     * @param  array<string, Environment>  $environments
     * @param  array<string, Flag>  $flags
     */
    private function seedAuditEvents(Project $project, array $environments, array $flags): void
    {
        $checkout = AuditEvent::query()->firstOrNew(['id' => self::CHECKOUT_AUDIT_ID]);

        if (! $checkout->exists) {
            $checkout->id = self::CHECKOUT_AUDIT_ID;
        }

        $checkout->fill([
            'project_id' => $project->id,
            'environment_id' => $environments['production']->id,
            'flag_id' => $flags['checkout']->id,
            'action' => 'flag.updated',
            'target_type' => 'flag',
            'target_id' => $flags['checkout']->id,
            'actor_type' => 'user',
            'actor_identifier' => 'dan@phlag.test',
            'changes' => [
                'is_enabled' => ['old' => false, 'new' => true],
            ],
            'context' => [
                'ip_address' => '10.20.30.40',
            ],
            'occurred_at' => Carbon::now()->subHour(),
        ]);

        $checkout->save();

        $projectAudit = AuditEvent::query()->firstOrNew(['id' => self::PROJECT_AUDIT_ID]);

        if (! $projectAudit->exists) {
            $projectAudit->id = self::PROJECT_AUDIT_ID;
        }

        $projectAudit->fill([
            'project_id' => $project->id,
            'environment_id' => null,
            'flag_id' => null,
            'action' => 'project.created',
            'target_type' => 'project',
            'target_id' => $project->id,
            'actor_type' => 'system',
            'actor_identifier' => 'seed@phlag.local',
            'changes' => [
                'metadata' => 'Initial project seed.',
            ],
            'context' => [
                'source' => 'seeder',
            ],
            'occurred_at' => Carbon::now()->subHours(2),
        ]);

        $projectAudit->save();
    }
}
