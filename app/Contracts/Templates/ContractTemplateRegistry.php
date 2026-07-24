<?php

namespace App\Contracts\Templates;

use Illuminate\Support\Collection;

class ContractTemplateRegistry
{
    /**
     * Register new contract templates here.
     *
     * @var array<int, class-string<ContractTemplate>>
     */
    protected const TEMPLATES = [
        AutoDvcTemplate::class,
    ];

    /** @var Collection<string, ContractTemplate>|null */
    protected ?Collection $instances = null;

    /**
     * @return Collection<string, ContractTemplate>
     */
    public function all(): Collection
    {
        return $this->instances ??= collect(self::TEMPLATES)
            ->map(fn (string $class) => new $class)
            ->keyBy(fn (ContractTemplate $template) => $template->key());
    }

    public function find(string $key): ?ContractTemplate
    {
        return $this->all()->get($key);
    }

    /**
     * @return array<int, string>
     */
    public function keys(): array
    {
        return $this->all()->keys()->all();
    }
}
