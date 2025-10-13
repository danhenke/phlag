<?php

declare(strict_types=1);

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

header('Content-Type: application/json');

http_response_code(200);

echo json_encode([
    'service' => 'phlag',
    'status' => 'ok',
    'timestamp' => (new DateTimeImmutable)->format(DATE_ATOM),
]);
