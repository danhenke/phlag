<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Phlag\Models\AuditEvent;
use Phlag\Models\Environment;
use Phlag\Models\Evaluation;
use Phlag\Models\Flag;
use Phlag\Models\Project;

it('migrates the database schema', function (): void {
    $this->artisan('app:migrate')
        ->assertExitCode(0);

    expect(Schema::hasTable('projects'))->toBeTrue()
        ->and(Schema::hasTable('environments'))->toBeTrue()
        ->and(Schema::hasTable('flags'))->toBeTrue()
        ->and(Schema::hasTable('evaluations'))->toBeTrue()
        ->and(Schema::hasTable('audit_events'))->toBeTrue();
});

it('seeds demo records after migrating', function (): void {
    $this->artisan('app:migrate')->assertExitCode(0);

    $this->artisan('app:seed')->assertExitCode(0);

    $project = Project::query()->where('key', 'demo-project')->with('environments')->first();

    if ($project === null) {
        throw new \RuntimeException('Demo project was not seeded.');
    }

    expect($project->environments)->toHaveCount(2);

    expect(Environment::query()
        ->where('project_id', $project->id)
        ->where('key', 'production')
        ->exists())->toBeTrue();

    expect(Flag::query()
        ->where('project_id', $project->id)
        ->where('key', 'checkout-redesign')
        ->exists())->toBeTrue();

    expect(Evaluation::query()->count())->toBe(2);
    expect(AuditEvent::query()->count())->toBe(2);
});

it('can seed repeatedly without creating duplicates', function (): void {
    $this->artisan('app:migrate')->assertExitCode(0);

    $this->artisan('app:seed')->assertExitCode(0);
    $this->artisan('app:seed')->assertExitCode(0);

    expect(Project::query()->where('key', 'demo-project')->count())->toBe(1);
    expect(Environment::query()->where('key', 'production')->count())->toBe(1);
    expect(Flag::query()->where('key', 'checkout-redesign')->count())->toBe(1);
    expect(Evaluation::query()->count())->toBe(2);
    expect(AuditEvent::query()->count())->toBe(2);
});
