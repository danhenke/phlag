<?php

declare(strict_types=1);

$memoryLimit = getenv('PHLAG_PHPSTAN_MEMORY_LIMIT') ?: '512M';
ini_set('memory_limit', $memoryLimit);

putenv('PHPSTAN_DISABLE_PARALLEL=1');
putenv('PHPSTAN_ALLOW_PARALLEL=0');
$_ENV['PHPSTAN_DISABLE_PARALLEL'] = '1';
$_SERVER['PHPSTAN_DISABLE_PARALLEL'] = '1';
$_ENV['PHPSTAN_ALLOW_PARALLEL'] = '0';
$_SERVER['PHPSTAN_ALLOW_PARALLEL'] = '0';

require __DIR__.'/../vendor/phpstan/phpstan/phpstan';
