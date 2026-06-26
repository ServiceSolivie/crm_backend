<?php

namespace App\Repositories\Eloquent;

use App\Models\DocumentType;
use App\Repositories\Contracts\DocumentTypeRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class DocumentTypeRepository extends BaseRepository implements DocumentTypeRepositoryInterface
{
    public function model(): string
    {
        return DocumentType::class;
    }

    public function activeOrdered(): Collection
    {
        return $this->newQuery()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }
}
