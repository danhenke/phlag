<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Bootstrap\SetRequestForConsole;
use LaravelZero\Framework\Testing\TestCase as BaseTestCase;
use Phlag\Auth\Jwt\Configuration;
use Phlag\Auth\Jwt\JwtTokenIssuer;
use Phlag\Auth\Jwt\JwtTokenVerifier;
use Tests\Support\TestKeys;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        /** @var Repository $config */
        $config = $this->app['config'];

        $config->set('database.default', 'sqlite');
        $config->set('database.connections.sqlite.database', ':memory:');
        $config->set('database.connections.sqlite.foreign_key_constraints', true);

        $config->set('jwt', [
            'keys' => [
                'active' => [
                    'id' => TestKeys::ACTIVE_KEY_ID,
                    'private_key' => TestKeys::activePrivateKey(),
                    'public_key' => TestKeys::activePublicKey(),
                ],
                'previous' => [
                    'id' => TestKeys::PREVIOUS_KEY_ID,
                    'public_key' => TestKeys::previousPublicKey(),
                ],
                'secret' => null,
            ],
            'ttl' => 600,
            'clock_skew' => 0,
        ]);

        $this->app->forgetInstance(Configuration::class);
        $this->app->forgetInstance(JwtTokenIssuer::class);
        $this->app->forgetInstance(JwtTokenVerifier::class);
    }

    public function createApplication(): Application
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();
        (new SetRequestForConsole)->bootstrap($app);

        return $app;
    }
}
