<?php

declare(strict_types=1);

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

$autoloadPath = __DIR__.'/../vendor/autoload.php';

if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
} else {
    $pharPath = getenv('PHLAG_PHAR') ?: __DIR__.'/../phlag';
    $resolvedPhar = realpath($pharPath);

    if ($resolvedPhar !== false) {
        $pharAutoload = sprintf('phar://%s/vendor/autoload.php', $resolvedPhar);

        if (file_exists($pharAutoload)) {
            require_once $pharAutoload;
        }
    }
}

/** @var Application $app */
$app = require __DIR__.'/../bootstrap/app.php';

/** @var Kernel $kernel */
$kernel = $app->make(Kernel::class);

$request = Request::capture();
$response = $kernel->handle($request);

$response->send();

$kernel->terminate($request, $response);
