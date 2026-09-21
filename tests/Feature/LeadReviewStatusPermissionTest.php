<?php

namespace Tests\Feature;

use App\Enums\LeadStatusEnum;
use App\Enums\RoleEnum;
use App\Models\Lead;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Agents may not set back-office statuses (Validé, Call2, PDG, À corriger);
 * they send leads to GESTION. Gestion and managers can set them.
 */
class LeadReviewStatusPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        Mail::fake();

        $this->seed([PermissionSeeder::class, RoleSeeder::class]);

        // The gestion role is created by a migration; make sure it holds the permission
        Role::findOrCreate(RoleEnum::GESTION->value, 'web')
            ->givePermissionTo(['leads.update_status', 'leads.view_gestion_assigned', 'leads.set_review_status']);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    private function leadFor(User $agent, LeadStatusEnum $status = LeadStatusEnum::DEVIS_ENVOYE, ?User $gestion = null): Lead
    {
        return Lead::create([
            'reference' => 'LD-TEST-'.Str::upper(Str::random(6)),
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'phone' => '0612345678',
            'insurance_type' => 'AUTO',
            'status' => $status->value,
            'assigned_to' => $agent->id,
            'gestion_assigned_to' => $gestion?->id,
            'created_by' => $agent->id,
            // Gestion / Validé need a signed DVC (covered in DvcPaymentStatusTest)
            'dvc_status' => 'SIGNE',
        ]);
    }

    public static function reviewStatuses(): array
    {
        return [
            'validé' => ['VALIDE', ['expected_revenue' => 500]],
            'call2 ok' => ['CALL2_OK', []],
            'call2 ko' => ['CALL2_KO', []],
            'pdg ok' => ['PDG_OK', []],
            'pdg ko' => ['PDG_KO', []],
            'à corriger' => ['A_CORRIGER', []],
        ];
    }

    #[DataProvider('reviewStatuses')]
    public function test_agent_cannot_set_a_review_status(string $status, array $extra): void
    {
        $agent = $this->userWithRole(RoleEnum::AGENT->value);
        $lead = $this->leadFor($agent);

        $this->actingAs($agent)
            ->patchJson("/api/v1/leads/{$lead->id}/status", ['status' => $status, ...$extra])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');

        $this->assertSame(LeadStatusEnum::DEVIS_ENVOYE, $lead->fresh()->status);
    }

    public function test_agent_can_send_a_lead_to_gestion(): void
    {
        $this->userWithRole(RoleEnum::GESTION->value); // someone must receive it
        $agent = $this->userWithRole(RoleEnum::AGENT->value);
        $lead = $this->leadFor($agent);

        $this->actingAs($agent)
            ->patchJson("/api/v1/leads/{$lead->id}/status", ['status' => 'GESTION'])
            ->assertOk();

        $this->assertSame(LeadStatusEnum::GESTION, $lead->fresh()->status);
    }

    public function test_agent_keeps_the_other_statuses(): void
    {
        $agent = $this->userWithRole(RoleEnum::AGENT->value);
        $lead = $this->leadFor($agent);

        $this->actingAs($agent)
            ->patchJson("/api/v1/leads/{$lead->id}/status", ['status' => 'PERDU'])
            ->assertOk();

        $this->assertSame(LeadStatusEnum::PERDU, $lead->fresh()->status);
    }

    #[DataProvider('reviewStatuses')]
    public function test_gestion_can_set_every_review_status(string $status, array $extra): void
    {
        $agent = $this->userWithRole(RoleEnum::AGENT->value);
        $gestion = $this->userWithRole(RoleEnum::GESTION->value);
        $lead = $this->leadFor($agent, LeadStatusEnum::GESTION, $gestion);

        $this->actingAs($gestion)
            ->patchJson("/api/v1/leads/{$lead->id}/status", ['status' => $status, ...$extra])
            ->assertOk();

        $this->assertSame($status, $lead->fresh()->status->value);
    }

    public function test_manager_can_still_validate(): void
    {
        // Managers see their own team's leads
        $team = Team::create(['name' => 'Équipe test', 'is_active' => true]);
        $agent = $this->userWithRole(RoleEnum::AGENT->value);
        $manager = $this->userWithRole(RoleEnum::MANAGER->value);
        $manager->update(['team_id' => $team->id]);
        $lead = $this->leadFor($agent);
        $lead->update(['team_id' => $team->id]);

        $this->actingAs($manager)
            ->patchJson("/api/v1/leads/{$lead->id}/status", ['status' => 'VALIDE', 'expected_revenue' => 720])
            ->assertOk();

        $this->assertSame(LeadStatusEnum::VALIDE, $lead->fresh()->status);
    }
}
