<?php

namespace App\Rules;

use App\Support\LeadStatusCatalog;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The status must be a system status or an active custom one.
 */
class ValidLeadStatus implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! app(LeadStatusCatalog::class)->selectable($value)) {
            $fail('Le statut sélectionné est invalide.');
        }
    }
}
