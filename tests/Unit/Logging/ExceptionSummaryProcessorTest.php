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

it('drops the exception from context after summarizing', function (): void {
    $processor = new ExceptionSummaryProcessor;

    $exception = new RuntimeException('Out of memory');

    $record = $processor([
        'message' => 'Processing payload',
        'context' => [
            'exception' => $exception,
            'job_id' => 42,
        ],
    ]);

    expect($record)->toHaveKey('context');

    expect($record['context'])
        ->toBeArray()
        ->not->toHaveKey('exception')
        ->toHaveKey('job_id');

    expect($record['context']['job_id'])->toBe(42);
});

it('adds a trimmed stack trace to the context', function (): void {
    $processor = new ExceptionSummaryProcessor;

    $exception = null;

    try {
        (function (): void {
            (function (): void {
                throw new RuntimeException('Stack failure');
            })();
        })();
    } catch (RuntimeException $caught) {
        $exception = $caught;
    }

    expect($exception)->not->toBeNull();

    $record = $processor([
        'message' => 'Something exploded',
        'context' => ['exception' => $exception],
    ]);

    expect($record['context'])->toHaveKey('stack_trace');

    $trace = $record['context']['stack_trace'];

    expect($trace)->toBeString();

    $lines = explode(PHP_EOL, $trace);

    foreach ($lines as $line) {
        expect($line)->toBe(trim($line))->not->toBe('');
    }
});
