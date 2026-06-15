<?php

namespace App\Exceptions;

class ForbiddenException extends ApiException
{
    public function __construct(string $message = 'This action is unauthorized')
    {
        parent::__construct($message, 403);
    }
}
