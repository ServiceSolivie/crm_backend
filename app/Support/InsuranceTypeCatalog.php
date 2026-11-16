<?php

namespace App\Support;

use App\Models\InsuranceType;
use App\Models\LeadPage;

/**
 * The insurance types and the lead pages they are placed on, read once per
 * request. A code no longer in the table still reads as itself.
 */
class InsuranceTypeCatalog
{
    /**
     * @var array<string, array{label: string, short_label: string, is_active: bool, requires_company_info: bool, page: ?string}>|null
     */
    private ?array $types = null;

    /**
     * @var array<string, array{id: int, name: string}>|null
     */
    private ?array $pages = null;

    /**
     * Keyed by code, in the admin's order.
     *
     * @return array<string, array{label: string, short_label: string, is_active: bool, requires_company_info: bool, page: ?string}>
     */
    public function all(): array
    {
        if ($this->types !== null) {
            return $this->types;
        }

        $slugs = collect($this->pages())->mapWithKeys(fn (array $page, string $slug) => [$page['id'] => $slug]);

        return $this->types = InsuranceType::query()
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get()
            ->mapWithKeys(fn (InsuranceType $type) => [$type->code => [
                'label' => $type->label,
                'short_label' => $type->short_label,
                'is_active' => $type->is_active,
                'requires_company_info' => $type->requires_company_info,
                'page' => $type->lead_page_id ? $slugs->get($type->lead_page_id) : null,
            ]])
            ->all();
    }

    /**
     * Extra lead pages keyed by slug.
     *
     * @return array<string, array{id: int, name: string}>
     */
    public function pages(): array
    {
        return $this->pages ??= LeadPage::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug'])
            ->mapWithKeys(fn (LeadPage $page) => [$page->slug => ['id' => $page->id, 'name' => $page->name]])
            ->all();
    }

    public function forget(): void
    {
        $this->types = null;
        $this->pages = null;
    }

    /**
     * @return array<int, string>
     */
    public function codes(): array
    {
        return array_map('strval', array_keys($this->all()));
    }

    public function exists(?string $code): bool
    {
        return $code !== null && isset($this->all()[$code]);
    }

    /**
     * Codes a lead can be given now: a deactivated type stays on its leads
     * but is no longer offered.
     *
     * @return array<int, string>
     */
    public function selectable(): array
    {
        return array_map('strval', array_keys(array_filter($this->all(), fn (array $type) => $type['is_active'])));
    }

    public function label(?string $code): ?string
    {
        return $code === null ? null : ($this->all()[$code]['label'] ?? $code);
    }

    public function shortLabel(?string $code): ?string
    {
        return $code === null ? null : ($this->all()[$code]['short_label'] ?? $code);
    }

    public function requiresCompanyInfo(?string $code): bool
    {
        return $code !== null && ($this->all()[$code]['requires_company_info'] ?? false);
    }

    /**
     * Codes placed on the given extra page.
     *
     * @return array<int, string>
     */
    public function codesForPage(string $slug): array
    {
        return array_map('strval', array_keys(array_filter($this->all(), fn (array $type) => $type['page'] === $slug)));
    }

    /**
     * Codes placed on any extra page: they leave the « Leads » page.
     *
     * @return array<int, string>
     */
    public function pagedCodes(): array
    {
        return array_map('strval', array_keys(array_filter($this->all(), fn (array $type) => $type['page'] !== null)));
    }

    /**
     * Label from a free text (imports): a code or a label, any case.
     */
    public function findCode(?string $value): ?string
    {
        $value = mb_strtolower(trim((string) $value));
        if ($value === '') {
            return null;
        }

        foreach ($this->all() as $code => $type) {
            if (in_array($value, [mb_strtolower((string) $code), mb_strtolower($type['label']), mb_strtolower($type['short_label'])], true)) {
                return (string) $code;
            }
        }

        return null;
    }
}
