<?php

declare(strict_types=1);

namespace Phlag\Logging;

use Throwable;

final class ExceptionSummaryProcessor
{
    /**
     * Invoke the processor.
     *
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    public function __invoke(array $record): array
    {
        $context = $record['context'] ?? null;

        if (! is_array($context)) {
            return $record;
        }

        $exception = $context['exception'] ?? null;

        if (! $exception instanceof Throwable) {
            return $record;
        }

        $message = $this->stringify($record['message'] ?? '');
        $exceptionMessage = $exception->getMessage();

        $summary = sprintf(
            '%s:%d %s: %s',
            $this->relativePath($exception->getFile()),
            $exception->getLine(),
            class_basename($exception::class),
            $this->normalizeMessage($exception->getMessage())
        );

        if ($message !== '' && $message !== $exceptionMessage) {
            $summary .= sprintf(' | %s', $this->collapseWhitespace($message));
        }

        $record['message'] = $summary;
        $contextWithoutException = $context;
        unset($contextWithoutException['exception']);

        $stackTrace = $this->stackTrace($exception);

        if ($stackTrace !== null) {
            $contextWithoutException['stack_trace'] = $stackTrace;
        }

        $record['context'] = $contextWithoutException;

        return $record;
    }

    /**
     * Build a normalized stack trace without blank lines.
     */
    private function stackTrace(Throwable $exception): ?string
    {
        $lines = preg_split('/\r?\n/', $exception->getTraceAsString()) ?: [];

        $normalized = array_values(array_filter(array_map(static function (string $line): string {
            return trim($line);
        }, $lines), static function (string $line): bool {
            return $line !== '';
        }));

        if ($normalized === []) {
            return null;
        }

        return implode(PHP_EOL, $normalized);
    }

    private function normalizeMessage(?string $message): string
    {
        $trimmed = trim((string) $message);

        if ($trimmed === '') {
            return '(no message)';
        }

        $normalized = preg_replace('/\s+/', ' ', $trimmed);

        return $normalized === null ? $trimmed : $normalized;
    }

    private function relativePath(string $path): string
    {
        $basePath = base_path();

        if ($path !== '' && str_starts_with($path, $basePath)) {
            return ltrim(str_replace($basePath, '', $path), DIRECTORY_SEPARATOR);
        }

        return $path;
    }

    private function collapseWhitespace(string $value): string
    {
        $normalized = preg_replace('/\s+/', ' ', trim($value));

        return $normalized === null ? trim($value) : $normalized;
    }

    private function stringify(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }

        return '';
    }
}
