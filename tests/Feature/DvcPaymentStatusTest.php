<?php

namespace Tests\Feature;

use App\Enums\DvcStatusEnum;
use App\Enums\LeadStatusEnum;
use App\Enums\PaymentStatusEnum;
use App\Enums\RoleEnum;
use App\Models\Lead;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * DVC track (generated → waiting for signature → signed) and payment
 * statuses (per payment and per lead).
 */
class DvcPaymentStatusTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    private User $gestion;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        Mail::fake();
        Storage::fake('local');

        $this->seed([PermissionSeeder::class, RoleSeeder::class]);
        Role::findOrCreate(RoleEnum::GESTION->value, 'web')->givePermissionTo([
            'leads.update_status', 'leads.view_gestion_assigned', 'leads.set_review_status',
            'documents.view', 'documents.upload', 'documents.delete', 'payments.view',
        ]);

        $this->agent = $this->userWithRole(RoleEnum::AGENT->value);
        $this->gestion = $this->userWithRole(RoleEnum::GESTION->value);
        $this->admin = $this->userWithRole(RoleEnum::SUPER_ADMIN->value);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    private function lead(array $attributes = []): Lead
    {
        return Lead::create([
            'reference' => 'LD-T-'.Str::upper(Str::random(8)),
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'phone' => '0612345678',
            'insurance_type' => 'RC_PRO',
            'status' => LeadStatusEnum::DEVIS_ENVOYE->value,
            'assigned_to' => $this->agent->id,
            'created_by' => $this->agent->id,
            ...$attributes,
        ])->refresh();
    }

    private function uploadDvc(Lead $lead)
    {
        return $this->postJson("/api/v1/leads/{$lead->id}/documents", [
            'document_type' => 'DVC',
            'file' => UploadedFile::fake()->create('dvc-signe.pdf', 120, 'application/pdf'),
        ]);
    }

    private function pay(Lead $lead, array $data)
    {
        return $this->postJson("/api/v1/leads/{$lead->id}/payments", [
            'payment_date' => now()->toDateString(),
            'payment_method' => 'VIREMENT_BANCAIRE',
            ...$data,
        ]);
    }

    // ── DVC ───────────────────────────────────────────────────────────

    public function test_new_leads_start_with_dvc_to_generate(): void
    {
        $lead = $this->lead();

        $this->assertSame(DvcStatusEnum::A_GENERER, $lead->dvc_status);
        $this->actingAs($this->agent)->getJson("/api/v1/leads/{$lead->id}")
            ->assertJsonPath('data.dvc_status', 'A_GENERER')
            ->assertJsonPath('data.dvc_status_label', 'DVC à générer');
    }

    public function test_generating_a_dvc_waits_for_the_signature(): void
    {
        $lead = $this->lead(['insurance_type' => 'AUTO', 'client_type' => 'INDIVIDUAL']);

        $this->actingAs($this->agent)->postJson('/api/v1/contracts', [
            'template_key' => 'auto_dvc',
            'lead_id' => $lead->id,
            'data' => ['first_name' => 'Jean', 'last_name' => 'Dupont'],
        ])->assertCreated();

        $this->assertSame(DvcStatusEnum::EN_ATTENTE_SIGNATURE, $lead->fresh()->dvc_status);
    }

    public function test_the_dvc_slot_is_first_in_every_dossier(): void
    {
        $lead = $this->lead();

        $documents = $this->actingAs($this->agent)
            ->getJson("/api/v1/leads/{$lead->id}/documents")
            ->assertOk()
            ->json('data.documents');

        $this->assertSame('DVC', $documents[0]['type']);
        $this->assertSame('missing', $documents[0]['status']);
    }

    public function test_uploading_the_signed_dvc_marks_it_signed_and_deleting_it_reverts(): void
    {
        $lead = $this->lead(['dvc_status' => 'EN_ATTENTE_SIGNATURE']);
        \App\Models\Contract::create([
            'reference' => 'CT-TEST', 'template_key' => 'auto_dvc', 'version' => 1, 'lead_id' => $lead->id,
            'client_name' => 'Jean Dupont', 'data' => [], 'original_filename' => 'dvc.pdf',
            'file_path' => 'contracts/x.pdf', 'mime_type' => 'application/pdf', 'file_size' => 1,
            'generated_by' => $this->agent->id,
        ]);

        $this->actingAs($this->agent);
        $documentId = $this->uploadDvc($lead)->assertSuccessful()->json('data.id');

        $fresh = $lead->fresh();
        $this->assertSame(DvcStatusEnum::SIGNE, $fresh->dvc_status);
        $this->assertNotNull($fresh->dvc_signed_at);

        // Gestion removes a bad copy → back to waiting for the signature
        $this->actingAs($this->admin)
            ->deleteJson("/api/v1/leads/{$lead->id}/documents/{$documentId}")
            ->assertSuccessful();
        $this->assertSame(DvcStatusEnum::EN_ATTENTE_SIGNATURE, $lead->fresh()->dvc_status);
    }

    public function test_the_dvc_can_be_uploaded_before_the_client_type_is_known(): void
    {
        $lead = $this->lead(['insurance_type' => 'AUTO', 'client_type' => null]);

        $this->actingAs($this->agent);
        $this->uploadDvc($lead)->assertSuccessful();
        $this->assertSame(DvcStatusEnum::SIGNE, $lead->fresh()->dvc_status);

        $this->postJson("/api/v1/leads/{$lead->id}/documents", [
            'document_type' => 'RIB',
            'file' => UploadedFile::fake()->create('rib.pdf', 50, 'application/pdf'),
        ])->assertStatus(422);
    }

    public function test_gestion_needs_a_signed_dvc(): void
    {
        $lead = $this->lead();

        $this->actingAs($this->agent)
            ->patchJson("/api/v1/leads/{$lead->id}/status", ['status' => 'GESTION'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');

        $this->uploadDvc($lead)->assertSuccessful();

        $this->patchJson("/api/v1/leads/{$lead->id}/status", ['status' => 'GESTION'])->assertOk();
        $this->assertSame(LeadStatusEnum::GESTION, $lead->fresh()->status);
    }

    public function test_validation_needs_a_signed_dvc_but_no_revenue(): void
    {
        $lead = $this->lead(['status' => 'GESTION', 'gestion_assigned_to' => $this->gestion->id]);

        $this->actingAs($this->gestion)
            ->patchJson("/api/v1/leads/{$lead->id}/status", ['status' => 'VALIDE'])
            ->assertStatus(422);

        $lead->update(['dvc_status' => 'SIGNE']);

        $this->patchJson("/api/v1/leads/{$lead->id}/status", ['status' => 'VALIDE'])->assertOk();
        $fresh = $lead->fresh();
        $this->assertSame(LeadStatusEnum::VALIDE, $fresh->status);
        $this->assertNotNull($fresh->validated_at);
    }

    public function test_the_dvc_document_type_is_locked_in_the_settings(): void
    {
        $dvc = \App\Models\DocumentType::where('name', 'DVC')->firstOrFail();
        $this->actingAs($this->admin);

        $this->getJson('/api/v1/document-types')
            ->assertOk()
            ->assertJsonFragment(['name' => 'DVC', 'is_system' => true]);

        $matrix = $this->getJson('/api/v1/document-requirements')->assertOk()->json('data');
        $this->assertSame('DVC', $matrix['always_required'][0]['name']);
        $this->assertNotContains('DVC', array_column($matrix['document_types'], 'name'));

        $this->deleteJson("/api/v1/document-types/{$dvc->id}")->assertStatus(409);
        $this->putJson("/api/v1/document-types/{$dvc->id}", ['label' => 'DVC signé', 'is_active' => false])->assertStatus(422);
        $this->putJson("/api/v1/document-types/{$dvc->id}", ['label' => 'DVC signé client', 'is_active' => true])->assertOk();
        $this->assertSame('DVC signé client', $dvc->fresh()->label);
    }

    // ── Payments ──────────────────────────────────────────────────────

    public function test_payment_before_signature_asks_for_the_contract_total(): void
    {
        $lead = $this->lead();
        $this->actingAs($this->agent);

        $this->pay($lead, ['amount' => 100])->assertStatus(422)->assertJsonValidationErrors('expected_revenue');

        $this->pay($lead, ['amount' => 100, 'expected_revenue' => 400])
            ->assertCreated()
            ->assertJsonPath('data.status', 'REUSSI')
            ->assertJsonPath('data.source', 'MANUEL');

        $fresh = $lead->fresh();
        $this->assertEquals(400, $fresh->expected_revenue);
        $this->assertSame(PaymentStatusEnum::PARTIELLEMENT_PAYE, $fresh->payment_status);
        $this->assertSame(DvcStatusEnum::A_GENERER, $fresh->dvc_status);

        $this->pay($lead, ['amount' => 300])->assertCreated();
        $this->assertSame(PaymentStatusEnum::PAYE, $lead->fresh()->payment_status);

        $this->pay($lead, ['amount' => 1])->assertStatus(422)->assertJsonValidationErrors('amount');
    }

    public function test_lost_leads_cannot_receive_payments(): void
    {
        $lead = $this->lead(['status' => 'PERDU']);

        $this->actingAs($this->agent)
            ->pay($lead, ['amount' => 100, 'expected_revenue' => 400])
            ->assertStatus(422);
    }

    public function test_pending_payment_then_received(): void
    {
        $lead = $this->lead();
        $this->actingAs($this->agent);

        $id = $this->pay($lead, ['amount' => 400, 'expected_revenue' => 400, 'status' => 'EN_ATTENTE'])
            ->assertCreated()->json('data.id');
        $this->assertSame(PaymentStatusEnum::EN_ATTENTE, $lead->fresh()->payment_status);
        $this->assertSame('0.00', $lead->fresh()->total_received);

        $this->patchJson("/api/v1/leads/{$lead->id}/payments/{$id}/status", ['status' => 'REUSSI'])
            ->assertOk()
            ->assertJsonPath('data.status', 'REUSSI')
            ->assertJsonPath('data.status_changed_by.id', $this->agent->id);
        $this->assertSame(PaymentStatusEnum::PAYE, $lead->fresh()->payment_status);
    }

    public function test_failed_and_cancelled_payments_do_not_count(): void
    {
        $lead = $this->lead();
        $this->actingAs($this->agent);

        $a = $this->pay($lead, ['amount' => 200, 'expected_revenue' => 400, 'status' => 'EN_ATTENTE'])->json('data.id');
        $b = $this->pay($lead, ['amount' => 200, 'status' => 'EN_ATTENTE'])->json('data.id');

        $this->patchJson("/api/v1/leads/{$lead->id}/payments/{$a}/status", ['status' => 'ECHOUE', 'reason' => 'Virement rejeté'])
            ->assertOk()->assertJsonPath('data.failure_reason', 'Virement rejeté');
        $this->assertSame(PaymentStatusEnum::EN_ATTENTE, $lead->fresh()->payment_status);

        $this->patchJson("/api/v1/leads/{$lead->id}/payments/{$b}/status", ['status' => 'ANNULE'])->assertOk();
        $this->assertSame(PaymentStatusEnum::NON_PAYE, $lead->fresh()->payment_status);

        // A failed payment is final
        $this->patchJson("/api/v1/leads/{$lead->id}/payments/{$a}/status", ['status' => 'REUSSI'])->assertStatus(422);

        // The released amount can be collected again
        $this->pay($lead, ['amount' => 400])->assertCreated();
        $this->assertSame(PaymentStatusEnum::PAYE, $lead->fresh()->payment_status);
    }

    public function test_refunds_need_the_delete_permission_and_mark_the_lead_refunded(): void
    {
        $lead = $this->lead();
        $this->actingAs($this->agent);
        $id = $this->pay($lead, ['amount' => 400, 'expected_revenue' => 400])->json('data.id');

        // Agents cannot refund
        $this->patchJson("/api/v1/leads/{$lead->id}/payments/{$id}/status", ['status' => 'REMBOURSE'])->assertForbidden();

        $this->actingAs($this->admin)
            ->patchJson("/api/v1/leads/{$lead->id}/payments/{$id}/status", ['status' => 'REMBOURSE'])
            ->assertOk();

        $fresh = $lead->fresh();
        $this->assertSame(PaymentStatusEnum::REMBOURSE, $fresh->payment_status);
        $this->assertSame('0.00', $fresh->total_received);
    }

    public function test_leads_can_be_filtered_by_dvc_and_payment_status(): void
    {
        $signed = $this->lead(['dvc_status' => 'SIGNE']);
        $paid = $this->lead(['payment_status' => 'PAYE', 'expected_revenue' => 100]);
        $this->lead();

        $this->actingAs($this->admin);

        $this->assertSame([$signed->id], collect($this->getJson('/api/v1/leads?dvc_status=SIGNE')->json('data'))->pluck('id')->all());
        $this->assertSame([$paid->id], collect($this->getJson('/api/v1/leads?payment_status[]=PAYE&payment_status[]=REMBOURSE')->json('data'))->pluck('id')->all());
    }
}
