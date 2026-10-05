<?php

namespace App\Services\Ringover;

use App\Exceptions\ApiException;
use App\Exceptions\RingoverException;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Support\Collection;

/**
 * Links CRM users to their Ringover user, so calls reported by Ringover
 * can be attributed to the right agent.
 */
class RingoverUserLinker
{
    public function __construct(protected RingoverClient $client) {}

    /**
     * CRM users with their link state, plus the Ringover users of the account.
     */
    public function overview(): array
    {
        // Still list CRM users when Ringover is down or not configured yet.
        $ringoverError = null;
        try {
            $ringoverUsers = collect($this->client->users());
        } catch (RingoverException $e) {
            $ringoverUsers = collect();
            $ringoverError = $e->getMessage();
        }
        $crmUsers = $this->crmUsers();
        $linkedIds = $this->linkedRingoverIds();

        return [
            'crm_users' => $crmUsers->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->getRoleNames(),
                'team' => $user->team?->only(['id', 'name']),
                'is_linked' => $user->isRingoverLinked(),
                'ringover_user_id' => $user->ringover_user_id,
                'ringover_number' => $user->ringover_number,
                'ringover_linked_at' => $user->ringover_linked_at?->toIso8601String(),
                // A linked id that no longer exists on Ringover (user deleted there).
                'is_stale' => $ringoverError === null
                    && $user->isRingoverLinked()
                    && ! $ringoverUsers->contains('user_id', $user->ringover_user_id),
                'suggested_ringover_user_id' => $user->isRingoverLinked()
                    ? null
                    : $this->matchByEmail($user, $ringoverUsers, $linkedIds)['user_id'] ?? null,
            ])->values()->all(),
            'ringover_users' => $ringoverUsers->map(fn (array $ro) => $ro + [
                'linked_crm_user_id' => $crmUsers->firstWhere('ringover_user_id', $ro['user_id'])?->id,
            ])->values()->all(),
            'ringover_error' => $ringoverError,
        ];
    }

    /**
     * Link every unlinked CRM user whose email matches a free Ringover user.
     *
     * @return array{linked: array<int, array>, unmatched: array<int, array>}
     */
    public function autoLink(): array
    {
        $ringoverUsers = collect($this->client->users(fresh: true));
        $crmUsers = $this->crmUsers();
        $linkedIds = $this->linkedRingoverIds();

        $linked = [];
        $unmatched = [];

        foreach ($crmUsers->reject->isRingoverLinked() as $user) {
            $match = $this->matchByEmail($user, $ringoverUsers, $linkedIds);

            if (! $match) {
                $unmatched[] = ['id' => $user->id, 'name' => $user->name, 'email' => $user->email];

                continue;
            }

            $this->applyLink($user, $match['user_id'], $match['numbers'][0] ?? null);
            $linkedIds[] = $match['user_id'];
            $linked[] = ['id' => $user->id, 'name' => $user->name, 'ringover_user_id' => $match['user_id']];
        }

        return ['linked' => $linked, 'unmatched' => $unmatched];
    }

    /**
     * Manually link a CRM user to a Ringover user. When no number is given,
     * the Ringover user's first number is used.
     */
    public function link(User $user, string $ringoverUserId, ?string $number = null): User
    {
        $ringoverUser = $this->client->findUser($ringoverUserId);

        if (! $ringoverUser) {
            // The cached list may predate a user just added on Ringover.
            $this->client->users(fresh: true);
            $ringoverUser = $this->client->findUser($ringoverUserId);
        }

        if (! $ringoverUser) {
            throw new ApiException('Utilisateur Ringover introuvable.', 422, [
                'ringover_user_id' => ['Cet utilisateur n\'existe pas sur le compte Ringover.'],
            ]);
        }

        $owner = User::where('ringover_user_id', $ringoverUserId)->whereKeyNot($user->id)->first();
        if ($owner) {
            throw new ApiException('Utilisateur Ringover déjà lié.', 422, [
                'ringover_user_id' => ["Déjà lié à {$owner->name}."],
            ]);
        }

        $number = $number !== null ? PhoneNumber::toE164($number) : ($ringoverUser['numbers'][0] ?? null);

        if ($number !== null && ! in_array($number, $ringoverUser['numbers'], true)) {
            throw new ApiException('Numéro invalide.', 422, [
                'ringover_number' => ['Ce numéro n\'appartient pas à cet utilisateur Ringover.'],
            ]);
        }

        return $this->applyLink($user, $ringoverUserId, $number);
    }

    public function unlink(User $user): User
    {
        $user->update([
            'ringover_user_id' => null,
            'ringover_number' => null,
            'ringover_linked_at' => null,
        ]);

        return $user->refresh();
    }

    protected function applyLink(User $user, string $ringoverUserId, ?string $number): User
    {
        $user->update([
            'ringover_user_id' => $ringoverUserId,
            'ringover_number' => $number,
            'ringover_linked_at' => now(),
        ]);

        return $user->refresh();
    }

    protected function matchByEmail(User $user, Collection $ringoverUsers, array $takenIds): ?array
    {
        $email = mb_strtolower(trim($user->email));

        return $ringoverUsers->first(
            fn (array $ro) => $ro['email'] === $email && ! in_array($ro['user_id'], $takenIds, true)
        );
    }

    /**
     * Ringover ids already taken, including by deactivated users.
     */
    protected function linkedRingoverIds(): array
    {
        return User::whereNotNull('ringover_user_id')->pluck('ringover_user_id')->all();
    }

    /**
     * @return Collection<int, User>
     */
    protected function crmUsers(): Collection
    {
        return User::query()
            ->with(['roles', 'team'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }
}
