<?php

namespace App\Exceptions;

use App\Http\Responses\ApiResponse;
use Exception;
use Illuminate\Http\JsonResponse;

/**
 * Base exception for all expected, user-facing API errors.
 *
 * Throw this (or a subclass) from the Service layer when a business rule
 * is violated; it will be rendered as a standard API error response.
 */
class ApiException extends Exception
{
    protected int $statusCode = 400;

    protected mixed $errors = null;

    public function __construct(string $message = 'An error occurred', int $statusCode = 400, mixed $errors = null)
    {
        parent::__construct($message);

        $this->statusCode = $statusCode;
        $this->errors = $errors;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getErrors(): mixed
    {
        return $this->errors;
    }

    /**
     * Details for the error log (and its Slack alert) only; never sent back
     * to the client.
     *
     * @var array<string, mixed>
     */
    protected array $logContext = [];

    /**
     * @param  array<string, mixed>  $context
     */
    public function withLogContext(array $context): static
    {
        $this->logContext = $context;

        return $this;
    }

    /**
     * Picked up by Laravel's exception handler and added to the log entry.
     *
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->logContext;
    }

    public function render(): JsonResponse
    {
        return ApiResponse::error($this->getMessage(), $this->statusCode, $this->errors);
    }
}
