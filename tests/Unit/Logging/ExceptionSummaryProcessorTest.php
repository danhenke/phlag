<?php

declare(strict_types=1);

use Phlag\Logging\ExceptionSummaryProcessor;

it('builds a summary from an exception', function (): void {
    $processor = new ExceptionSummaryProcessor;

    $exception = new RuntimeException('Boom');

    $record = $processor([
        'message' => 'Boom',
        'context' => ['exception' => $exception],
    ]);

    expect($record['message'])
        ->toContain('tests/Unit/Logging/ExceptionSummaryProcessorTest.php')
        ->toContain('RuntimeException: Boom');
});

it('appends original message when different from exception message', function (): void {
    $processor = new ExceptionSummaryProcessor;

    $exception = new RuntimeException('Explosion');

    $record = $processor([
        'message' => 'Processing payment failed',
        'context' => ['exception' => $exception],
    ]);

    expect($record['message'])
        ->toContain('tests/Unit/Logging/ExceptionSummaryProcessorTest.php')
        ->toContain('RuntimeException: Explosion')
        ->toContain('Processing payment failed');
});
