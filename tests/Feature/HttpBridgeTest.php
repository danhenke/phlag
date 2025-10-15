<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use Illuminate\Testing\Fluent\AssertableJson;
use Symfony\Component\HttpFoundation\Response;

it('returns a JSON health payload from the HTTP bridge', function (): void {
    $response = $this->getJson('/');

    $response->assertOk()
        ->assertJson(fn (AssertableJson $json) => $json
            ->where('service', 'Phlag')
            ->where('status', 'ok')
            ->has('timestamp')
        );
});

it('logs request metadata for HTTP bridge invocations', function (): void {
    Log::shouldReceive('error')->zeroOrMoreTimes();

    Log::shouldReceive('info')
        ->once()
        ->withArgs(function (string $message, array $context): bool {
            expect($message)->toBe('HTTP request handled');
            expect($context['method'])->toBe('GET');
            expect($context['uri'])->toBe('/');
            expect($context['status'])->toBe(Response::HTTP_OK);
            expect($context['user_agent'])->not()->toBeNull();
            expect($context)->toHaveKey('duration_ms');
            expect($context)->toHaveKey('ip');

            return true;
        });

    $this->getJson('/')->assertOk();
});
