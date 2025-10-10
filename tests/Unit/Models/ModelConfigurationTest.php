<?php

declare(strict_types=1);

use Phlag\Models\AuditEvent;
use Phlag\Models\Environment;
use Phlag\Models\Evaluation;
use Phlag\Models\Flag;
use Phlag\Models\Project;

it('configures project model casting and key handling', function (): void {
    $project = new Project();

    expect($project->incrementing)->toBeFalse()
        ->and($project->getKeyType())->toBe('string')
        ->and($project->getCasts())->toHaveKey('metadata');
});

it('configures environment model casting and key handling', function (): void {
    $environment = new Environment();

    expect($environment->incrementing)->toBeFalse()
        ->and($environment->getKeyType())->toBe('string')
        ->and($environment->getCasts())->toMatchArray([
            'is_default' => 'bool',
            'metadata' => 'array',
        ]);
});

it('configures flag model casting and key handling', function (): void {
    $flag = new Flag();

    expect($flag->incrementing)->toBeFalse()
        ->and($flag->getKeyType())->toBe('string')
        ->and($flag->getCasts())->toMatchArray([
            'is_enabled' => 'bool',
            'variants' => 'array',
            'rules' => 'array',
        ]);
});

it('configures evaluation model casting and key handling', function (): void {
    $evaluation = new Evaluation();

    expect($evaluation->incrementing)->toBeFalse()
        ->and($evaluation->getKeyType())->toBe('string')
        ->and($evaluation->getCasts())->toMatchArray([
            'request_context' => 'array',
            'evaluation_payload' => 'array',
            'evaluated_at' => 'datetime',
        ]);
});

it('configures audit event model casting and key handling', function (): void {
    $auditEvent = new AuditEvent();

    expect($auditEvent->incrementing)->toBeFalse()
        ->and($auditEvent->getKeyType())->toBe('string')
        ->and($auditEvent->getCasts())->toMatchArray([
            'changes' => 'array',
            'context' => 'array',
            'occurred_at' => 'datetime',
        ]);
});
