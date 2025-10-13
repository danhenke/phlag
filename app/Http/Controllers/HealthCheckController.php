<?php

declare(strict_types=1);

namespace Phlag\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

final class HealthCheckController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'service' => config('app.name', 'phlag'),
            'status' => 'ok',
            'timestamp' => Carbon::now()->toIso8601String(),
        ]);
    }
}
