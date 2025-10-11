<?php

declare(strict_types=1);

putenv('PHPSTAN_DISABLE_PARALLEL=1');
putenv('PHPSTAN_ALLOW_PARALLEL=0');
$_ENV['PHPSTAN_DISABLE_PARALLEL'] = '1';
$_SERVER['PHPSTAN_DISABLE_PARALLEL'] = '1';
$_ENV['PHPSTAN_ALLOW_PARALLEL'] = '0';
$_SERVER['PHPSTAN_ALLOW_PARALLEL'] = '0';

require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/../vendor/laravel-zero/foundation/src/Illuminate/Foundation/helpers.php';
