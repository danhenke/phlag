<?php

declare(strict_types=1);

require_once __DIR__.'/../vendor/autoload.php';

header('Content-Type: application/json');

http_response_code(200);

echo json_encode([
    'service' => 'phlag',
    'status' => 'ok',
    'timestamp' => (new DateTimeImmutable)->format(DATE_ATOM),
]);
