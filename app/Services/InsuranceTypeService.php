<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\DocumentRequirement;
use App\Models\DvcType;
use App\Models\InsuranceType;
use App\Models\Lead;
use App\Models\LeadPage;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Insurance types and the extra lead pages they can be placed on.
 */
class InsuranceTypeService
{
    public function listTypes(): Collection
    {
        return InsuranceType::query()
            ->withCount('leads')
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get();
    }

    public function createType(array $attributes): InsuranceType
    {
        $attributes['is_active'] = $attributes['is_active'] ?? true;
        $attributes['is_system'] = false;
        $attributes['sort_order'] = $attributes['sort_order'] ?? ((int) InsuranceType::max('sort_order') + 1);

        return InsuranceType::create($attributes)->loadCount('leads');
    }

    public function updateType(InsuranceType $type, array $attributes): InsuranceType
    {
        unset($attributes['code'], $attributes['is_system']);

        $type->update($attributes);

        return $type->refresh()->loadCount('leads');
    }

    /**
     * Only a type nothing points to can go: otherwise deactivate it.
     */
    public function deleteType(InsuranceType $type): bool
    {
        if ($type->is_system) {
            throw new ApiException('Ce type fait partie du système : désactivez-le au lieu de le supprimer.', 409);
        }

        $used = Lead::withTrashed()->where('insurance_type', $type->code)->exists()
            || DvcType::query()->where('insurance_type', $type->code)->exists()
            || DocumentRequirement::query()->where('insurance_type', $type->code)->exists();

        if ($used) {
            throw new ApiException('Ce type est utilisé (leads, DVC ou documents requis) : désactivez-le au lieu de le supprimer.', 409);
        }

        return (bool) $type->delete();
    }

    public function listPages(): Collection
    {
        return LeadPage::query()->orderBy('sort_order')->orderBy('name')->get();
    }

    public function createPage(array $attributes): LeadPage
    {
        return LeadPage::create([
            'name' => $attributes['name'],
            'slug' => $this->uniqueSlug($attributes['name']),
            'sort_order' => $attributes['sort_order'] ?? ((int) LeadPage::max('sort_order') + 1),
        ]);
    }

    /**
     * The slug stays: it is in menu links people may have bookmarked.
     */
    public function updatePage(LeadPage $page, array $attributes): LeadPage
    {
        $page->update(array_intersect_key($attributes, array_flip(['name', 'sort_order'])));

        return $page->refresh();
    }

    /**
     * Its types go back to the « Leads » page.
     */
    public function deletePage(LeadPage $page): bool
    {
        return DB::transaction(function () use ($page) {
            InsuranceType::query()->where('lead_page_id', $page->id)->get()->each->update(['lead_page_id' => null]);

            return (bool) $page->delete();
        });
    }

    protected function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'page';
        $slug = $base;
        for ($i = 2; LeadPage::query()->where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }
}
