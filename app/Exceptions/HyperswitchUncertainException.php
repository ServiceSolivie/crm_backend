<?php

namespace App\Exceptions;

/**
 * Hyperswitch did not give a clear answer (timeout, dropped connection,
 * server error, incomplete response): the payment MAY exist. The request
 * must not be retried blindly — check it with retrievePayment() instead.
 */
class HyperswitchUncertainException extends ApiException
{
    public function __construct(string $message = 'Hyperswitch n\'a pas confirmé la demande.', int $statusCode = 504)
    {
        parent::__construct($message, $statusCode);
    }
}
