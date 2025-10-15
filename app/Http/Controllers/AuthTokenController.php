<?php

declare(strict_types=1);

namespace Phlag\Http\Controllers;

use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;
use Phlag\Auth\ApiKeys\TokenExchangeResult;
use Phlag\Auth\ApiKeys\TokenExchangeService;
use Phlag\Http\Requests\IssueTokenRequest;
use Phlag\Http\Responses\ApiErrorResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

final class AuthTokenController extends Controller
{
    public function __construct(
        private readonly TokenExchangeService $tokenExchange
    ) {}

    /**
     * Exchange a project/environment API key for a JWT bearer token.
     */
    #[OA\Post(
        path: '/v1/auth/token',
        operationId: 'exchangeApiKeyForJwt',
        summary: 'Issue JWT from API key',
        tags: ['Authentication'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/AuthTokenRequest')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'JWT issued successfully.',
                content: new OA\JsonContent(ref: '#/components/schemas/AuthTokenResponse')
            ),
            new OA\Response(
                response: 400,
                description: 'Request body contained malformed JSON.',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(
                response: 401,
                description: 'Invalid credentials supplied.',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(
                response: 404,
                description: 'Project or environment not found.',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(
                response: 422,
                description: 'Validation failed.',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(
                response: 429,
                description: 'Too many requests.',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(
                response: 500,
                description: 'Unexpected server error.',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
        ]
    )]
    public function store(IssueTokenRequest $request): JsonResponse
    {
        /** @var array{project: string, environment: string, api_key: string} $payload */
        $payload = $request->validated();

        $result = $this->tokenExchange->exchange(
            $payload['project'],
            $payload['environment'],
            $payload['api_key']
        );

        if (! $result->isSuccessful()) {
            return $this->errorResponse($result, $payload);
        }

        $token = $result->token();
        $project = $result->project();
        $environment = $result->environment();

        return response()->json([
            'token' => $token?->value(),
            'token_type' => 'Bearer',
            'expires_in' => $result->expiresIn(),
            'project' => $project?->key,
            'environment' => $environment?->key,
            'roles' => $result->roles(),
        ], HttpResponse::HTTP_OK);
    }

    /**
     * @param  array{project: string, environment: string, api_key: string}  $payload
     */
    private function errorResponse(TokenExchangeResult $result, array $payload): JsonResponse
    {
        $context = [
            'project' => $payload['project'],
            'environment' => $payload['environment'],
        ];

        return match ($result->errorCode()) {
            TokenExchangeResult::ERROR_PROJECT_NOT_FOUND => ApiErrorResponse::make(
                'resource_not_found',
                'Project not found.',
                HttpResponse::HTTP_NOT_FOUND,
                context: $context,
                detail: $result->errorMessage()
            ),
            TokenExchangeResult::ERROR_ENVIRONMENT_NOT_FOUND => ApiErrorResponse::make(
                'resource_not_found',
                'Environment not found for project.',
                HttpResponse::HTTP_NOT_FOUND,
                context: $context,
                detail: $result->errorMessage()
            ),
            TokenExchangeResult::ERROR_CREDENTIAL_INVALID => ApiErrorResponse::make(
                'unauthorized',
                'Authentication failed for the provided API key.',
                HttpResponse::HTTP_UNAUTHORIZED,
                context: $context,
                detail: $result->errorMessage()
            ),
            TokenExchangeResult::ERROR_CREDENTIAL_INACTIVE => ApiErrorResponse::make(
                'unauthorized',
                'The API key is inactive.',
                HttpResponse::HTTP_UNAUTHORIZED,
                context: $context,
                detail: $result->errorMessage()
            ),
            TokenExchangeResult::ERROR_CREDENTIAL_EXPIRED => ApiErrorResponse::make(
                'unauthorized',
                'The API key has expired.',
                HttpResponse::HTTP_UNAUTHORIZED,
                context: $context,
                detail: $result->errorMessage()
            ),
            default => ApiErrorResponse::make(
                'server_error',
                'Unable to issue token due to an unexpected error.',
                HttpResponse::HTTP_INTERNAL_SERVER_ERROR,
                context: $context,
                detail: $result->errorMessage()
            ),
        };
    }
}
