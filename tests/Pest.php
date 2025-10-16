<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

uses(
    Tests\TestCase::class,
    Tests\Support\InteractsWithFlagCache::class,
    Tests\Support\RecordsDatabaseQueries::class
)->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

use Phlag\Auth\Jwt\JwtTokenIssuer;

function something(): void
{
    // ..
}

/**
 * @param  array<string, mixed>  $claims
 * @return array<string, string>
 */
function jwtHeaders(array $claims = []): array
{
    /** @var JwtTokenIssuer $issuer */
    $issuer = app(JwtTokenIssuer::class);

    $defaultClaims = [
        'sub' => 'test-suite',
        'roles' => [
            'projects.read',
            'projects.manage',
            'environments.read',
            'environments.manage',
            'flags.read',
            'flags.manage',
            'flags.evaluate',
            'cache.warm',
        ],
    ];

    $token = $issuer->issue(array_merge($defaultClaims, $claims));

    return [
        'Authorization' => 'Bearer '.$token->value(),
    ];
}
