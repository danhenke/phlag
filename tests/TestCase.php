<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Bootstrap\SetRequestForConsole;
use LaravelZero\Framework\Testing\TestCase as BaseTestCase;

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
    }

    public function createApplication(): Application
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();
        (new SetRequestForConsole)->bootstrap($app);

        return $app;
    }
}
