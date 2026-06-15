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

    public function render(): JsonResponse
    {
        return ApiResponse::error($this->getMessage(), $this->statusCode, $this->errors);
    }
}
