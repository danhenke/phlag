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

it('marks API endpoints as not implemented yet', function (string $method, string $uri): void {
    $response = match ($method) {
        'POST' => $this->postJson($uri, []),
        'PATCH' => $this->patchJson($uri, []),
        'DELETE' => $this->deleteJson($uri),
        default => $this->getJson($uri),
    };

    $response->assertStatus(Response::HTTP_NOT_IMPLEMENTED)
        ->assertJson(fn (AssertableJson $json) => $json
            ->where('error.code', 'not_implemented')
            ->where('error.status', Response::HTTP_NOT_IMPLEMENTED)
            ->where('error.context.endpoint', strtoupper($method).' '.$uri)
            ->where('error.message', fn (string $message) => str_contains($message, 'not available yet'))
            ->has('error.detail')
        );
})->with([
    ['POST', '/v1/auth/token'],
]);

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
