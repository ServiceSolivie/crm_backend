<?php

namespace App\Repositories\Contracts;

use Illuminate\Database\Eloquent\Collection;

interface DocumentTypeRepositoryInterface extends RepositoryInterface
{
    public function activeOrdered(): Collection;
}
