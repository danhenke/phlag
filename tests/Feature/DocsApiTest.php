<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\Response;

it('serves the generated OpenAPI specification as JSON', function (): void {
    $response = $this->get('/v1/openapi.json');

    $response->assertOk();

    $specPath = base_path('docs/openapi.json');
    $payload = json_decode(File::get($specPath), true, flags: JSON_THROW_ON_ERROR);

    expect($response->headers->get('Content-Type'))
        ->toContain('application/json');

    expect(json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR))
        ->toEqual($payload);
});

it('returns a standardized error when the OpenAPI artifact is missing', function (): void {
    $specPath = base_path('docs/openapi.json');
    $backupPath = $specPath.'.bak';

    expect(File::exists($specPath))->toBeTrue();

    File::move($specPath, $backupPath);

    try {
        $this->getJson('/v1/openapi.json')
            ->assertStatus(Response::HTTP_NOT_FOUND)
            ->assertJson([
                'error' => [
                    'code' => 'resource_not_found',
                    'status' => Response::HTTP_NOT_FOUND,
                ],
            ]);
    } finally {
        if (File::exists($backupPath)) {
            File::move($backupPath, $specPath);
        }
    }
});

it('renders Swagger UI backed by the generated spec', function (): void {
    $response = $this->get('/docs');

    $response->assertOk();
    $response->assertSee('/v1/openapi.json', false);

    expect($response->headers->get('Content-Type'))
        ->toContain('text/html');
});

it('serves the Postman collection artifact', function (): void {
    $response = $this->get('/v1/postman.json');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/json');

    $collectionPath = base_path('postman/postman.json');
    $payload = json_decode(File::get($collectionPath), true, flags: JSON_THROW_ON_ERROR);

    expect(json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR))
        ->toEqual($payload);
});

it('returns a standardized error when the Postman collection is missing', function (): void {
    $collectionPath = base_path('postman/postman.json');
    $backupPath = $collectionPath.'.bak';

    expect(File::exists($collectionPath))->toBeTrue();

    File::move($collectionPath, $backupPath);

    try {
        $this->getJson('/v1/postman.json')
            ->assertStatus(Response::HTTP_NOT_FOUND)
            ->assertJson([
                'error' => [
                    'code' => 'resource_not_found',
                    'status' => Response::HTTP_NOT_FOUND,
                ],
            ]);
    } finally {
        if (File::exists($backupPath)) {
            File::move($backupPath, $collectionPath);
        }
    }
});
