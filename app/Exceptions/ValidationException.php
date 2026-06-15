<?php

namespace App\Exceptions;

class ValidationException extends ApiException
{
    public function __construct(string $message = 'The given data was invalid', mixed $errors = null)
    {
        parent::__construct($message, 422, $errors);
    }
}
