<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Http\Kernel as HttpKernelContract;
use Illuminate\Contracts\View\Factory as ViewFactoryContract;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\Request;
use Illuminate\Routing\RoutingServiceProvider;
use Illuminate\Validation\ValidationException;
use LaravelZero\Framework\Application;
use LaravelZero\Framework\Providers\GitVersion\GitVersionServiceProvider;
use Phlag\Http\Kernel as HttpKernel;
use Phlag\Http\Responses\ApiErrorResponse;
use Phlag\Providers\RouteServiceProvider as PhlagRouteServiceProvider;
use Phlag\Support\View\NullViewFactory;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: dirname(__DIR__).'/routes/api.php',
    )
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(static fn (Request $request, \Throwable $exception): bool => true);

        $exceptions->render(function (ValidationException $exception) {
            return ApiErrorResponse::validation(
                $exception->errors(),
                detail: $exception->getMessage()
            );
        });

        $exceptions->render(function (AuthenticationException $exception) {
            return ApiErrorResponse::make(
                'unauthorized',
                $exception->getMessage() !== '' ? $exception->getMessage() : 'Authentication is required for this resource.',
                HttpResponse::HTTP_UNAUTHORIZED
            );
        });

        $exceptions->render(function (AuthorizationException $exception) {
            return ApiErrorResponse::make(
                'forbidden',
                $exception->getMessage() !== '' ? $exception->getMessage() : 'You are not authorized to perform this action.',
                HttpResponse::HTTP_FORBIDDEN
            );
        });

        $exceptions->render(function (ModelNotFoundException $exception) {
            return ApiErrorResponse::make(
                'resource_not_found',
                'The requested resource could not be found.',
                HttpResponse::HTTP_NOT_FOUND
            );
        });

        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) {
            $status = $exception->getStatusCode();

            $code = match ($status) {
                HttpResponse::HTTP_BAD_REQUEST => 'bad_request',
                HttpResponse::HTTP_UNAUTHORIZED => 'unauthorized',
                HttpResponse::HTTP_FORBIDDEN => 'forbidden',
                HttpResponse::HTTP_NOT_FOUND => 'resource_not_found',
                HttpResponse::HTTP_METHOD_NOT_ALLOWED => 'method_not_allowed',
                HttpResponse::HTTP_CONFLICT => 'conflict',
                HttpResponse::HTTP_UNPROCESSABLE_ENTITY => 'validation_failed',
                HttpResponse::HTTP_TOO_MANY_REQUESTS => 'rate_limited',
                HttpResponse::HTTP_NOT_IMPLEMENTED => 'not_implemented',
                default => 'http_error',
            };

            $originalMessage = $exception->getMessage();
            $detail = null;

            if ((bool) config('app.debug') && $originalMessage !== '') {
                $detail = $originalMessage;
            }

            $message = $originalMessage;

            if ($message === '') {
                $message = match ($status) {
                    HttpResponse::HTTP_NOT_FOUND => 'The requested endpoint could not be found.',
                    HttpResponse::HTTP_METHOD_NOT_ALLOWED => 'The request method is not allowed for this endpoint.',
                    HttpResponse::HTTP_UNAUTHORIZED => 'Authentication is required for this resource.',
                    HttpResponse::HTTP_FORBIDDEN => 'You are not authorized to perform this action.',
                    default => HttpResponse::$statusTexts[$status] ?? 'HTTP error',
                };
            }
            if ($status === HttpResponse::HTTP_NOT_FOUND) {
                $message = 'The requested resource could not be found.';
            }

            $context = [];

            if (in_array($status, [HttpResponse::HTTP_NOT_FOUND, HttpResponse::HTTP_METHOD_NOT_ALLOWED], true)) {
                $context['endpoint'] = sprintf(
                    '%s %s',
                    strtoupper($request->getMethod()),
                    $request->getPathInfo() ?: '/'
                );
            }

            $headers = method_exists($exception, 'getHeaders') ? $exception->getHeaders() : [];

            if ($status === HttpResponse::HTTP_TOO_MANY_REQUESTS && $headers !== []) {
                if (isset($headers['Retry-After'])) {
                    $context['retry_after'] = $headers['Retry-After'];
                }
            }

            return ApiErrorResponse::make(
                $code,
                $message,
                $status,
                $context,
                detail: $detail,
                headers: $headers
            );
        });

        $exceptions->render(function (\Throwable $exception) {
            $debug = (bool) config('app.debug');

            $detail = $debug ? $exception->getMessage() : null;

            $context = $debug ? [
                'exception' => $exception::class,
            ] : [];

            return ApiErrorResponse::make(
                'server_error',
                'An unexpected error occurred.',
                HttpResponse::HTTP_INTERNAL_SERVER_ERROR,
                $context,
                detail: $detail
            );
        });
    })
    ->create();

$app->singleton(Filesystem::class, fn () => new Filesystem);
$app->alias(Filesystem::class, 'files');
$app->singleton(ViewFactoryContract::class, fn () => new NullViewFactory);
$app->alias(ViewFactoryContract::class, 'view');

$app->register(RoutingServiceProvider::class);
$app->register(PhlagRouteServiceProvider::class);
(new GitVersionServiceProvider($app))->register();

$app->singleton(HttpKernelContract::class, HttpKernel::class);

return $app;
