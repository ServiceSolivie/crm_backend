<?php

namespace App\Rules;

use App\Enums\LeadStatusEnum;
use App\Enums\PermissionEnum;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Auth;

/**
 * Back-office statuses (Validé, Call2 OK/KO, PDG OK/KO, À corriger) may only
 * be set by users holding leads.set_review_status. Agents send a lead to the
 * back office by setting GESTION; gestion takes it from there.
 */
class ReviewStatusAllowed implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $status = LeadStatusEnum::tryFrom((string) $value);

        if ($status === null || ! $status->isReviewStatus()) {
            return;
        }

        $user = Auth::user();

        if (! $user || ! $user->can(PermissionEnum::LEADS_SET_REVIEW_STATUS->value)) {
            $fail('Seule la gestion peut appliquer le statut « '.$status->label().' ». Passez le lead en « Gestion » pour l\'envoyer en revue.');
        }
    }
}
