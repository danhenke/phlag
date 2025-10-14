<?php

declare(strict_types=1);

namespace Phlag\Http\Responses;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

final class ApiErrorResponse
{
    /**
     * Build a standardized API error response.
     *
     * @param  array<string, mixed>  $context
     * @param  array<int, array<string, mixed>>|null  $violations
     */
    public static function make(
        string $code,
        string $message,
        int $status,
        array $context = [],
        ?array $violations = null,
        ?string $detail = null,
    ): JsonResponse {
        $error = [
            'code' => $code,
            'message' => $message,
            'status' => $status,
        ];

        if ($detail !== null && $detail !== '') {
            $error['detail'] = $detail;
        }

        if ($context !== []) {
            $error['context'] = $context;
        }

        if ($violations !== null && $violations !== []) {
            $error['violations'] = $violations;
        }

        return response()->json(['error' => $error], $status);
    }

    /**
     * Build a standardized validation error response.
     *
     * @param  array<string, array<int, string>>  $errors
     */
    public static function validation(array $errors, ?string $detail = null): JsonResponse
    {
        $violations = [];

        foreach ($errors as $field => $messages) {
            foreach ($messages as $message) {
                $violations[] = [
                    'field' => $field,
                    'message' => $message,
                ];
            }
        }

        return self::make(
            'validation_failed',
            'Validation failed for the submitted payload.',
            HttpResponse::HTTP_UNPROCESSABLE_ENTITY,
            violations: $violations,
            detail: $detail
        );
    }
}
