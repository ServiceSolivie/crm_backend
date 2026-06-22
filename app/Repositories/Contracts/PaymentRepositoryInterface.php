<?php

namespace App\Repositories\Contracts;

interface PaymentRepositoryInterface extends RepositoryInterface
{
    public function sumForLead(int $leadId): string;
}
