<?php

namespace Tests\Feature;

use App\Enums\LeadStatusEnum;
use App\Enums\RoleEnum;
use App\Models\Appointment;
use App\Models\Lead;
use App\Models\LeadCall;
use App\Models\Payment;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Endpoints and fields added for the redesigned frontend
 * (see crm_front/docs/REDESIGN_BACKEND_TODO.md).
 */
class RedesignApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $agent;

    private User $otherAgent;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        Mail::fake();

        $this->seed([PermissionSeeder::class, RoleSeeder::class]);
        Role::findOrCreate(RoleEnum::GESTION->value, 'web')->givePermissionTo([
            'leads.update_status', 'leads.view_gestion_assigned', 'leads.set_review_status', 'gestion.flag_documents',
        ]);

        $this->team = Team::create(['name' => 'Équipe A', 'is_active' => true]);
        $this->admin = $this->userWithRole(RoleEnum::SUPER_ADMIN->value);
        $this->agent = $this->userWithRole(RoleEnum::AGENT->value, $this->team);
        $this->otherAgent = $this->userWithRole(RoleEnum::AGENT->value, $this->team);
    }

    private function userWithRole(string $role, ?Team $team = null): User
    {
        $user = User::factory()->create(['is_active' => true, 'team_id' => $team?->id]);
        $user->assignRole($role);

        return $user;
    }

    private function lead(array $attributes = []): Lead
    {
        return Lead::create([
            'reference' => 'LD-T-'.Str::upper(Str::random(8)),
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'phone' => '06'.random_int(10000000, 99999999),
            'insurance_type' => 'AUTO',
            'status' => LeadStatusEnum::NOUVEAU->value,
            'assigned_to' => $this->agent->id,
            'team_id' => $this->team->id,
            'created_by' => $this->admin->id,
            'dvc_status' => 'SIGNE',
            ...$attributes,
        ]);
    }

    private function appointment(Lead $lead, string $when, string $status = 'PLANIFIE', array $extra = []): Appointment
    {
        return Appointment::create([
            'lead_id' => $lead->id,
            'agent_id' => $lead->assigned_to ?? $this->agent->id,
            'scheduled_at' => $when,
            'status' => $status,
            'created_by' => $this->admin->id,
            ...$extra,
        ]);
    }

    private function ids($response): array
    {
        return collect($response->json('data'))->pluck('id')->sort()->values()->all();
    }

    // ── Lead list filters (2.5, 3.1, 3.5) ────────────────────────────────

    public function test_lead_list_accepts_several_statuses_and_a_stage(): void
    {
        $rappel = $this->lead(['status' => 'RAPPEL']);
        $attente = $this->lead(['status' => 'EN_ATTENTE_CLIENT']);
        $this->lead(['status' => 'NOUVEAU']);

        $this->actingAs($this->admin);

        $both = [$rappel->id, $attente->id];
        $this->assertSame($both, $this->ids($this->getJson('/api/v1/leads?status[]=RAPPEL&status[]=EN_ATTENTE_CLIENT')));
        $this->assertSame($both, $this->ids($this->getJson('/api/v1/leads?status=RAPPEL,EN_ATTENTE_CLIENT')));
        $this->assertSame($both, $this->ids($this->getJson('/api/v1/leads?stage=follow_up')));
        $this->assertSame([], $this->ids($this->getJson('/api/v1/leads?stage=unknown')));
    }

    public function test_lead_list_filters_unassigned_doublons_and_due(): void
    {
        $unassigned = $this->lead(['assigned_to' => null]);
        $doublon = $this->lead(['is_doublon' => true]);
        $late = $this->lead();
        $this->appointment($late, now()->subDay()->toDateTimeString());
        $later = $this->lead();
        $this->appointment($later, now()->addDays(3)->toDateTimeString());
        $done = $this->lead();
        $this->appointment($done, now()->subHour()->toDateTimeString(), 'REALISE');

        $this->actingAs($this->admin);

        $this->assertSame([$unassigned->id], $this->ids($this->getJson('/api/v1/leads?unassigned=1')));
        $this->assertSame([$doublon->id], $this->ids($this->getJson('/api/v1/leads?is_doublon=1')));
        $this->assertSame([$late->id], $this->ids($this->getJson('/api/v1/leads?due=today')));
    }

    public function test_internal_filter_helpers_cannot_be_called_from_the_query_string(): void
    {
        $this->lead();

        $this->actingAs($this->admin)
            ->getJson('/api/v1/leads?where_in=x&values=y&sort_by_next_action_at=1')
            ->assertOk();
    }

    // ── Next action + sort (3.3) ─────────────────────────────────────────

    public function test_lead_list_returns_next_action_and_sorts_on_it(): void
    {
        $soon = $this->lead();
        $this->appointment($soon, now()->addHour()->toDateTimeString());
        $this->appointment($soon, now()->addDays(2)->toDateTimeString());
        $late = $this->lead();
        $this->appointment($late, now()->subDay()->toDateTimeString());
        $none = $this->lead();
        $fix = $this->lead(['status' => 'A_CORRIGER']);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/leads?sort_by=next_action_at&sort_dir=asc')
            ->assertOk();

        $rows = collect($response->json('data'))->keyBy('id');
        $this->assertSame([$late->id, $soon->id], collect($response->json('data'))->pluck('id')->take(2)->all());
        $this->assertSame('appointment', $rows[$soon->id]['next_action']['type']);
        $this->assertFalse($rows[$soon->id]['next_action']['overdue']);
        $this->assertTrue($rows[$late->id]['next_action']['overdue']);
        $this->assertNull($rows[$none->id]['next_action']);
        $this->assertSame('missing_document', $rows[$fix->id]['next_action']['type']);
    }

    // ── Counts, search, duplicates (1.1, 3.2, 5.1) ──────────────────────

    public function test_counts_follow_the_user_scope(): void
    {
        $this->lead();
        $this->lead(['is_doublon' => true]);
        $this->lead(['assigned_to' => $this->otherAgent->id]);
        $this->lead(['assigned_to' => null]);

        $this->actingAs($this->agent)->getJson('/api/v1/leads/counts')
            ->assertOk()
            ->assertJsonPath('data.all', 2)
            ->assertJsonPath('data.mine', 2)
            ->assertJsonPath('data.doublons', 1)
            ->assertJsonPath('data.unassigned', 0);

        $this->actingAs($this->admin)->getJson('/api/v1/leads/counts')
            ->assertJsonPath('data.all', 4)
            ->assertJsonPath('data.unassigned', 1);
    }

    public function test_quick_search_matches_name_and_formatted_phone_within_scope(): void
    {
        $mine = $this->lead(['first_name' => 'Amélie', 'last_name' => 'Poulain', 'phone' => '0612345678']);
        $this->lead(['first_name' => 'Amélie', 'last_name' => 'Autre', 'assigned_to' => $this->otherAgent->id]);

        $this->actingAs($this->agent);

        $this->getJson('/api/v1/leads/search?q=Amélie Poul')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mine->id);

        $this->getJson('/api/v1/leads/search?q='.urlencode('06 12 34'))
            ->assertJsonPath('data.0.id', $mine->id);

        // The other agent's Amélie is not visible
        $this->getJson('/api/v1/leads/search?q=Amélie')->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/leads/search?q=a')->assertStatus(422);
    }

    public function test_duplicates_match_phone_digits_and_hide_leads_the_user_cannot_open(): void
    {
        $mine = $this->lead(['phone' => '06 12 34 56 78']);
        $other = $this->lead(['phone' => '+33612345678', 'assigned_to' => $this->otherAgent->id]);
        $this->lead(['phone' => '0700000000', 'email' => 'x@example.com']);

        $data = $this->actingAs($this->agent)
            ->getJson('/api/v1/leads/duplicates?phone=0612345678')
            ->assertOk()
            ->json('data');

        $byRef = collect($data)->keyBy('reference');
        $this->assertCount(2, $data);
        $this->assertTrue($byRef[$mine->reference]['can_open']);
        $this->assertSame($mine->id, $byRef[$mine->reference]['id']);
        $this->assertFalse($byRef[$other->reference]['can_open']);
        $this->assertNull($byRef[$other->reference]['id']);

        $this->getJson('/api/v1/leads/duplicates?email=x@example.com')->assertJsonCount(1, 'data');
        $this->getJson("/api/v1/leads/duplicates?phone=0612345678&exclude_id={$mine->id}")->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/leads/duplicates?phone=12')->assertJsonCount(0, 'data');
    }

    // ── Bulk (3.4) ───────────────────────────────────────────────────────

    public function test_bulk_status_reports_each_lead(): void
    {
        $a = $this->lead();
        $b = $this->lead();
        $notMine = $this->lead(['assigned_to' => $this->otherAgent->id]);

        $this->actingAs($this->agent)
            ->postJson('/api/v1/leads/bulk', ['ids' => [$a->id, $b->id, $notMine->id], 'action' => 'status', 'status' => 'RAPPEL'])
            ->assertOk()
            ->assertJsonPath('data.done', 2)
            ->assertJsonPath('data.failed', 1)
            ->assertJsonPath('data.results.2.ok', false);

        $this->assertSame(LeadStatusEnum::RAPPEL, $a->fresh()->status);
        $this->assertSame(LeadStatusEnum::NOUVEAU, $notMine->fresh()->status);
    }

    public function test_bulk_refuses_validation_and_review_statuses_for_agents(): void
    {
        $a = $this->lead();

        $this->actingAs($this->admin)
            ->postJson('/api/v1/leads/bulk', ['ids' => [$a->id], 'action' => 'status', 'status' => 'VALIDE'])
            ->assertStatus(422)->assertJsonValidationErrors('status');

        $this->actingAs($this->agent)
            ->postJson('/api/v1/leads/bulk', ['ids' => [$a->id], 'action' => 'status', 'status' => 'PDG_OK'])
            ->assertStatus(422)->assertJsonValidationErrors('status');
    }

    public function test_bulk_assign_and_delete(): void
    {
        $a = $this->lead(['assigned_to' => null]);
        $b = $this->lead(['assigned_to' => null]);

        $this->actingAs($this->admin)
            ->postJson('/api/v1/leads/bulk', ['ids' => [$a->id, $b->id], 'action' => 'assign', 'assigned_to' => $this->otherAgent->id])
            ->assertOk()->assertJsonPath('data.done', 2);
        $this->assertSame($this->otherAgent->id, $a->fresh()->assigned_to);

        // Agents cannot assign
        $this->actingAs($this->agent)
            ->postJson('/api/v1/leads/bulk', ['ids' => [$a->id], 'action' => 'assign', 'assigned_to' => $this->agent->id])
            ->assertOk()->assertJsonPath('data.done', 0);

        $this->actingAs($this->admin)
            ->postJson('/api/v1/leads/bulk', ['ids' => [$a->id, 999999], 'action' => 'delete'])
            ->assertOk()->assertJsonPath('data.done', 1)->assertJsonPath('data.results.1.error', 'Lead introuvable.');
        $this->assertSoftDeleted($a);
    }

    // ── Lead detail: activity, last flag, neighbours (4.1–4.3) ───────────

    public function test_activity_merges_everything_newest_first(): void
    {
        $lead = $this->lead();
        $this->actingAs($this->agent);

        $this->postJson("/api/v1/leads/{$lead->id}/notes", ['note' => 'Première note'])->assertCreated();
        $this->travel(1)->minutes();
        $this->postJson("/api/v1/leads/{$lead->id}/calls", ['outcome' => 'ANSWERED', 'note' => 'OK'])->assertSuccessful();
        $this->travel(1)->minutes();
        $this->patchJson("/api/v1/leads/{$lead->id}/status", ['status' => 'RAPPEL'])->assertOk();

        $response = $this->getJson("/api/v1/leads/{$lead->id}/activity?per_page=2")->assertOk();

        $this->assertSame(['status', 'call'], collect($response->json('data'))->pluck('type')->all());
        $this->assertSame(3, $response->json('meta.total'));
        $this->assertSame('Première note', $this->getJson("/api/v1/leads/{$lead->id}/activity?page=2&per_page=2")->json('data.0.data.note'));

        $this->actingAs($this->otherAgent)->getJson("/api/v1/leads/{$lead->id}/activity")->assertForbidden();
    }

    public function test_flag_issue_is_exposed_as_last_flag(): void
    {
        $gestion = $this->userWithRole(RoleEnum::GESTION->value);
        $lead = $this->lead(['status' => 'GESTION', 'gestion_assigned_to' => $gestion->id]);

        $this->actingAs($gestion)->postJson("/api/v1/leads/{$lead->id}/gestion/flag-issue", [
            'issue_types' => ['documents_manquants', 'information_manquante'],
            'missing_documents' => ['RIB', "Permis de conduire"],
            'comment' => 'Adresse incomplète',
        ])->assertOk();

        $this->actingAs($this->agent)->getJson("/api/v1/leads/{$lead->id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'A_CORRIGER')
            ->assertJsonPath('data.last_flag.message', 'Adresse incomplète')
            ->assertJsonPath('data.last_flag.documents', ['RIB', 'Permis de conduire'])
            ->assertJsonPath('data.last_flag.by.id', $gestion->id)
            ->assertJsonPath('data.next_action.type', 'missing_document');
    }

    public function test_neighbours_follow_the_list_filters(): void
    {
        $a = $this->lead(['status' => 'RAPPEL']);
        $this->lead(['status' => 'NOUVEAU']);
        $b = $this->lead(['status' => 'RAPPEL']);
        $c = $this->lead(['status' => 'RAPPEL']);
        foreach ([$a, $b, $c] as $i => $lead) {
            $lead->forceFill(['created_at' => now()->subHours(3 - $i)])->save();
        }

        $this->actingAs($this->admin)
            ->getJson("/api/v1/leads/{$b->id}/neighbours?status=RAPPEL")
            ->assertOk()
            ->assertJsonPath('data.prev_id', $a->id)
            ->assertJsonPath('data.next_id', $c->id)
            ->assertJsonPath('data.position', 2)
            ->assertJsonPath('data.total', 3);

        $this->getJson("/api/v1/leads/{$a->id}/neighbours?status=RAPPEL&sort_by=created_at&sort_dir=desc")
            ->assertJsonPath('data.next_id', null)
            ->assertJsonPath('data.prev_id', $b->id);
    }

    // ── Appointments (6.1–6.3) ───────────────────────────────────────────

    public function test_appointments_search_status_and_duration(): void
    {
        $lead = $this->lead(['first_name' => 'Zoé', 'last_name' => 'Martin', 'status' => 'DEVIS_ENVOYE']);
        $this->appointment($lead, now()->addDay()->toDateTimeString(), 'PLANIFIE', ['duration_minutes' => 45]);
        $this->appointment($this->lead(), now()->addDay()->addHour()->toDateTimeString());

        $response = $this->actingAs($this->admin)->getJson('/api/v1/appointments?search=Zoé Mar')->assertOk();

        $response->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.lead.status', 'DEVIS_ENVOYE')
            ->assertJsonPath('data.0.duration_minutes', 45);
        $this->assertNotNull($response->json('data.0.ends_at'));

        $this->getJson('/api/v1/appointments?status[]=PLANIFIE&status[]=REALISE')->assertJsonCount(2, 'data');
    }

    public function test_appointment_duration_can_be_set(): void
    {
        $lead = $this->lead();

        $id = $this->actingAs($this->admin)->postJson('/api/v1/appointments', [
            'lead_id' => $lead->id,
            'agent_id' => $this->agent->id,
            'scheduled_at' => now()->addDays(2)->setTime(10, 0)->toDateTimeString(),
            'duration_minutes' => 60,
        ])->assertCreated()->assertJsonPath('data.duration_minutes', 60)->json('data.id');

        $this->putJson("/api/v1/appointments/{$id}", ['duration_minutes' => 90])
            ->assertOk()->assertJsonPath('data.duration_minutes', 90);
    }

    // ── Me: counters and agenda (1.2, 2.4) ──────────────────────────────

    public function test_me_counters_and_agenda(): void
    {
        $late = $this->lead();
        $this->appointment($late, now()->subDays(10)->toDateTimeString());
        $today = $this->lead();
        $this->appointment($today, now()->endOfDay()->subMinute()->toDateTimeString());
        $fix = $this->lead(['status' => 'A_CORRIGER']);
        $this->appointment($this->lead(['assigned_to' => $this->otherAgent->id]), now()->subDay()->toDateTimeString());

        $this->actingAs($this->agent)->getJson('/api/v1/me/counters')
            ->assertOk()
            ->assertJsonPath('data.leads_total', 3)
            ->assertJsonPath('data.appointments_overdue', 1)
            ->assertJsonPath('data.appointments_today', 1)
            ->assertJsonPath('data.follow_ups_due', 2);

        $this->getJson('/api/v1/me/agenda')
            ->assertOk()
            ->assertJsonPath('data.counts.overdue', 1)
            ->assertJsonPath('data.overdue.0.lead.id', $late->id)
            ->assertJsonPath('data.today.0.lead.id', $today->id)
            ->assertJsonPath('data.tasks.0.lead.id', $fix->id);
    }

    // ── Dashboard KPIs (2.1–2.3) ────────────────────────────────────────

    public function test_dashboard_kpis_have_previous_period_unassigned_and_overdue(): void
    {
        $this->lead(['assigned_to' => null]);
        $old = $this->lead();
        $old->forceFill(['created_at' => now()->subDays(10)])->save();
        $this->appointment($old, now()->subHours(2)->toDateTimeString());

        $from = now()->subDays(6)->toDateString();
        $to = now()->toDateString();

        $this->actingAs($this->admin)
            ->getJson("/api/v1/dashboard/kpis?from={$from}&to={$to}")
            ->assertOk()
            ->assertJsonPath('data.leads.total', 1)
            ->assertJsonPath('data.leads.unassigned', 1)
            ->assertJsonPath('data.leads.previous.total', 1)
            ->assertJsonPath('data.appointments.overdue', 1);

        $this->getJson('/api/v1/dashboard/kpis')->assertJsonPath('data.leads.previous', null);
    }

    // ── Reports (7.1–7.3) ───────────────────────────────────────────────

    public function test_agent_and_team_reports_have_calls_and_revenue(): void
    {
        $lead = $this->lead(['status' => 'VALIDE', 'expected_revenue' => 1000, 'payment_status' => 'PARTIELLEMENT_PAYE', 'validated_at' => now()]);
        LeadCall::create(['lead_id' => $lead->id, 'user_id' => $this->agent->id, 'outcome' => 'ANSWERED']);
        LeadCall::create(['lead_id' => $lead->id, 'user_id' => $this->agent->id, 'outcome' => 'ANSWERED']);
        Payment::create(['lead_id' => $lead->id, 'amount' => 250, 'payment_date' => now()->toDateString(), 'payment_method' => 'VIREMENT_BANCAIRE', 'created_by' => $this->admin->id]);

        $this->actingAs($this->admin);

        $agents = collect($this->getJson('/api/v1/reports/agents?per_page=50')->assertOk()->json('data'))->keyBy('id');
        $this->assertSame(2, $agents[$this->agent->id]['calls']['total']);
        $this->assertEquals(250, $agents[$this->agent->id]['revenue']['received']);
        $this->assertSame(0, $agents[$this->otherAgent->id]['calls']['total']);

        $other = Team::create(['name' => 'Équipe B', 'is_active' => true]);
        $teams = $this->getJson("/api/v1/reports/teams?team_id={$this->team->id}")->assertOk()->json('data');
        $this->assertCount(1, $teams);
        $this->assertSame(2, $teams[0]['calls']['total']);
        $this->assertEquals(250, $teams[0]['revenue']['received']);

        $this->getJson("/api/v1/reports/conversion?group_by=agent&team_id={$other->id}")->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/reports/conversion?group_by=agent&team_id={$this->team->id}")->assertJsonCount(1, 'data');
    }

    public function test_report_summaries_cover_the_whole_filtered_set(): void
    {
        foreach (range(1, 3) as $i) {
            $this->lead(['status' => 'RAPPEL']);
        }
        $this->lead(['status' => 'VALIDE', 'expected_revenue' => 100, 'validated_at' => now()]);
        $lead = $this->lead();
        $this->appointment($lead, now()->subDay()->toDateTimeString());
        $this->appointment($lead, now()->subDays(2)->toDateTimeString(), 'REALISE');

        $this->actingAs($this->admin);

        $this->getJson('/api/v1/reports/leads/summary')
            ->assertOk()
            ->assertJsonPath('data.total', 5)
            ->assertJsonPath('data.validated', 1)
            ->assertJsonPath('data.conversion_rate', 20)
            ->assertJsonPath('data.by_stage.follow_up', 3);

        $this->getJson('/api/v1/reports/leads/summary?status=RAPPEL')->assertJsonPath('data.total', 3);

        $this->getJson('/api/v1/reports/appointments/summary')
            ->assertOk()
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.completed', 1)
            ->assertJsonPath('data.overdue', 1);

        $this->actingAs($this->agent)->getJson('/api/v1/reports/leads/summary')->assertForbidden();
    }
}
