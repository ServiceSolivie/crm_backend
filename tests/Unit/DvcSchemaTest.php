<?php

namespace Tests\Unit;

use App\Models\Lead;
use App\Models\User;
use App\Support\Dvc\DvcDocument;
use App\Support\Dvc\DvcFieldRules;
use App\Support\Dvc\DvcPrefill;
use App\Support\Dvc\DvcSchema;
use App\Support\Dvc\DvcSchemaValidator;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * DVC schemas without database: what the super admin's JSON may contain,
 * the rules the agent's values get, the prefill and the PDF formatting.
 */
class DvcSchemaTest extends TestCase
{
    private const CONFIG = [
        'broker' => ['name' => 'MOOV ASSUR', 'rcs' => 'SIREN 107 447 054'],
        'mediation' => ['name' => "La Médiation de l'Assurance"],
        'authority' => ['name' => 'ACPR'],
    ];

    private function validator(): DvcSchemaValidator
    {
        return new DvcSchemaValidator(['auto-hero.jpg'], self::CONFIG);
    }

    /**
     * @param  array<int, array<string, mixed>>  $sections
     * @return array<string, mixed>
     */
    private function schema(array $sections): array
    {
        return ['title' => 'Devoir de Conseil', 'sections' => $sections];
    }

    /**
     * @param  array<int, array<string, mixed>>  $fields
     * @return array<string, mixed>
     */
    private function fieldsSection(array $fields, string $key = 'main'): array
    {
        return ['type' => 'fields', 'key' => $key, 'title' => 'Section', 'fields' => $fields];
    }

    public function test_the_seeded_auto_and_decennale_schemas_are_valid(): void
    {
        foreach (['auto', 'decennale'] as $file) {
            $schema = json_decode(file_get_contents(dirname(__DIR__, 2)."/database/data/dvc/{$file}.json"), true);

            // Against the real broker config (config/contracts.php)
            $this->assertSame([], (new DvcSchemaValidator(['auto-hero.jpg']))->validate($schema), "{$file}.json");
        }
    }

    public function test_a_schema_needs_a_title_and_at_least_one_field(): void
    {
        $errors = $this->validator()->validate(['sections' => [['type' => 'broker', 'title' => 'Informations légales']]]);

        $this->assertArrayHasKey('schema.title', $errors);
        $this->assertArrayHasKey('sections', $errors);
        $this->assertSame(['schema' => 'Le schéma doit être un objet JSON.'], $this->validator()->validate([1, 2]));
    }

    public function test_unknown_types_and_properties_are_reported_with_their_path(): void
    {
        $errors = $this->validator()->validate($this->schema([
            ['type' => 'table', 'title' => 'X'],
            $this->fieldsSection([['key' => 'brand', 'lable' => 'Marque', 'type' => 'text']]),
        ]));

        $this->assertArrayHasKey('sections.0.type', $errors);
        $this->assertArrayHasKey('sections.1.fields.0.lable', $errors);
        $this->assertArrayHasKey('sections.1.fields.0.label', $errors);
    }

    public function test_keys_are_snake_case_and_unique_across_fields_and_guarantees(): void
    {
        $errors = $this->validator()->validate($this->schema([
            $this->fieldsSection([
                ['key' => 'Brand', 'label' => 'Marque', 'type' => 'text'],
                ['key' => 'model', 'label' => 'Modèle', 'type' => 'text'],
                ['key' => 'g_rc_included', 'label' => 'RC', 'type' => 'boolean'],
            ]),
            $this->fieldsSection([['key' => 'model', 'label' => 'Modèle', 'type' => 'text']], 'other'),
            ['type' => 'garanties', 'key' => 'garanties', 'title' => 'Garanties', 'items' => [['key' => 'g_rc', 'label' => 'RC']]],
        ]));

        $this->assertArrayHasKey('sections.0.fields.0.key', $errors);
        $this->assertArrayHasKey('sections.1.fields.0.key', $errors);
        // g_rc stores g_rc_included, already a field
        $this->assertArrayHasKey('sections.2.items.0.key', $errors);
    }

    public function test_field_options_bounds_prefill_and_defaults_must_fit_the_type(): void
    {
        $errors = $this->validator()->validate($this->schema([$this->fieldsSection([
            ['key' => 'usage', 'label' => 'Usage', 'type' => 'select'],
            ['key' => 'name', 'label' => 'Nom', 'type' => 'text', 'options' => ['a'], 'min' => 1],
            ['key' => 'prime', 'label' => 'Prime', 'type' => 'money', 'min' => 10, 'max' => 5],
            ['key' => 'source', 'label' => 'Source', 'type' => 'text', 'prefill' => 'lead.password'],
            ['key' => 'count', 'label' => 'Nombre', 'type' => 'number', 'default' => 'deux'],
            ['key' => 'civilite', 'label' => 'Civilité', 'type' => 'select', 'options' => ['M.', 'Mme'], 'default' => 'Mlle'],
        ])]));

        foreach (['0.options', '1.options', '1.min', '2.max', '3.prefill', '4.default', '5.default'] as $path) {
            $this->assertArrayHasKey("sections.0.fields.{$path}", $errors);
        }
    }

    public function test_placeholders_and_visible_if_must_point_to_a_known_value(): void
    {
        $errors = $this->validator()->validate($this->schema([
            ['type' => 'text', 'paragraphs' => ['Le client {{first_name}} chez {{broker.name}}.', 'Débit de {{montant}}.'], 'visible_if' => 'second_installment'],
            ['type' => 'list', 'intro' => 'Pour {{broker.secret}} :', 'items' => ['RIB']],
            // declared after the text that uses it: still fine
            $this->fieldsSection([['key' => 'first_name', 'label' => 'Prénom', 'type' => 'text']]),
        ]));

        $this->assertArrayNotHasKey('sections.0.paragraphs.0', $errors);
        $this->assertArrayHasKey('sections.0.paragraphs.1', $errors);
        $this->assertArrayHasKey('sections.0.visible_if', $errors);
        $this->assertArrayHasKey('sections.1.intro', $errors);
    }

    public function test_the_hero_image_must_be_one_of_the_available_images(): void
    {
        $schema = $this->schema([$this->fieldsSection([['key' => 'name', 'label' => 'Nom', 'type' => 'text']])]);

        $this->assertSame([], $this->validator()->validate([...$schema, 'hero_image' => 'auto-hero.jpg']));
        $this->assertArrayHasKey('schema.hero_image', $this->validator()->validate([...$schema, 'hero_image' => '../../.env']));
    }

    public function test_values_are_checked_against_the_schema(): void
    {
        $schema = new DvcSchema($this->schema([
            $this->fieldsSection([
                ['key' => 'last_name', 'label' => 'Nom', 'type' => 'text', 'required' => true],
                ['key' => 'prime', 'label' => 'Prime', 'type' => 'money', 'min' => 0],
                ['key' => 'usage', 'label' => 'Usage', 'type' => 'select', 'options' => ['Privé', 'Pro']],
                ['key' => 'start', 'label' => 'Début', 'type' => 'date'],
            ]),
            ['type' => 'garanties', 'key' => 'garanties', 'title' => 'Garanties', 'items' => [['key' => 'g_rc', 'label' => 'RC']]],
        ]));
        $rules = new DvcFieldRules;

        $valid = Validator::make(
            ['last_name' => 'Martin', 'prime' => '540.50', 'usage' => 'Pro', 'start' => '2026-11-01', 'g_rc_included' => true, 'g_rc_franchise' => '150 €'],
            $rules->rules($schema),
        );
        $this->assertTrue($valid->passes());

        $invalid = Validator::make(['prime' => -3, 'usage' => 'Loisir', 'start' => 'demain'], $rules->rules($schema), [], $rules->attributes($schema));
        $this->assertEqualsCanonicalizing(['last_name', 'prime', 'usage', 'start'], array_keys($invalid->errors()->messages()));
        $this->assertStringContainsString('Nom', $invalid->errors()->first('last_name'));

        $this->assertSame(['last_name', 'prime', 'usage', 'start', 'g_rc_included', 'g_rc_franchise'], $schema->dataKeys());
    }

    public function test_prefill_reads_whitelisted_sources_from_the_lead_and_its_agent(): void
    {
        $schema = new DvcSchema($this->schema([
            $this->fieldsSection([
                ['key' => 'first_name', 'label' => 'Prénom', 'type' => 'text', 'prefill' => 'lead.first_name'],
                ['key' => 'address', 'label' => 'Adresse', 'type' => 'text', 'prefill' => 'lead.full_address'],
                ['key' => 'employees', 'label' => 'Salariés', 'type' => 'text', 'prefill' => 'lead.company_employee_count'],
                ['key' => 'agent_name', 'label' => 'Conseiller', 'type' => 'text', 'prefill' => 'agent.name'],
                ['key' => 'mode', 'label' => 'Mode', 'type' => 'text', 'default' => 'Lien de paiement'],
                ['key' => 'email', 'label' => 'E-mail', 'type' => 'text', 'prefill' => 'lead.email', 'default' => 'aucun'],
            ]),
            ['type' => 'garanties', 'key' => 'garanties', 'title' => 'Garanties', 'items' => [
                ['key' => 'g_rc', 'label' => 'RC', 'default_included' => true],
                ['key' => 'g_vol', 'label' => 'Vol'],
            ]],
        ]));

        $agent = (new User)->forceFill(['name' => 'Sara Agent', 'email' => 'sara@example.test']);
        $lead = (new Lead)->forceFill(['first_name' => 'Karim', 'address' => '3 rue Haute', 'city' => 'Lyon', 'company_employee_count' => 12, 'email' => null]);
        $lead->setRelation('assignedAgent', $agent);

        $values = (new DvcPrefill)->values($schema, $lead, (new User)->forceFill(['name' => 'Manager']));

        $this->assertSame([
            'first_name' => 'Karim',
            'address' => '3 rue Haute Lyon',
            'employees' => '12',
            'agent_name' => 'Sara Agent',
            'mode' => 'Lien de paiement',
            'email' => 'aucun',
            'g_rc_included' => true,
        ], $values);

        // Without a lead: defaults and the user generating the DVC
        $this->assertSame(
            ['agent_name' => 'Manager', 'mode' => 'Lien de paiement', 'email' => 'aucun', 'g_rc_included' => true],
            (new DvcPrefill)->values($schema, null, (new User)->forceFill(['name' => 'Manager'])),
        );
    }

    public function test_the_pdf_formats_values_by_type_and_escapes_texts(): void
    {
        $schema = new DvcSchema($this->schema([$this->fieldsSection([
            ['key' => 'name', 'label' => 'Nom', 'type' => 'text'],
            ['key' => 'plate', 'label' => 'Immatriculation', 'type' => 'text', 'uppercase' => true],
            ['key' => 'prime', 'label' => 'Prime', 'type' => 'money'],
            ['key' => 'start', 'label' => 'Début', 'type' => 'date'],
            ['key' => 'pro', 'label' => 'Pro', 'type' => 'boolean'],
            ['key' => 'empty', 'label' => 'Vide', 'type' => 'text'],
        ])]));
        $fields = $schema->fields();

        $document = new DvcDocument($schema, [
            'name' => '<b>Martin</b>',
            'plate' => 'ab-123-cd',
            'prime' => '1234.5',
            'start' => '2026-11-01',
            'pro' => false,
        ], self::CONFIG);

        $this->assertSame('AB-123-CD', $document->value($fields['plate']));
        $this->assertSame('1 234,50 €', $document->value($fields['prime']));
        $this->assertSame('01/11/2026', $document->value($fields['start']));
        $this->assertSame('Non', $document->value($fields['pro']));

        $this->assertSame(
            '<strong>Note</strong> pour <span class="fill-inline">&lt;b&gt;Martin&lt;/b&gt;</span> '
            .'(<span class="fill-inline">&nbsp;</span>) chez MOOV ASSUR &amp; co : ',
            (string) $document->html('**Note** pour {{name}} ({{empty}}) chez {{broker.name}} & co : {{unknown}}'),
        );
    }

    public function test_fields_are_laid_out_in_rows_with_full_width_fields_alone(): void
    {
        $section = ['type' => 'fields', 'key' => 's', 'title' => 'S', 'columns' => 2, 'fields' => [
            ['key' => 'a', 'label' => 'A', 'type' => 'text'],
            ['key' => 'b', 'label' => 'B', 'type' => 'text'],
            ['key' => 'c', 'label' => 'C', 'type' => 'text'],
            ['key' => 'd', 'label' => 'D', 'type' => 'textarea', 'width' => 'full'],
            ['key' => 'e', 'label' => 'E', 'type' => 'text'],
        ]];
        $document = new DvcDocument(new DvcSchema($this->schema([$section])), [], self::CONFIG);

        $rows = array_map(fn ($row) => array_column($row, 'key'), $document->rows($section));

        $this->assertSame([['a', 'b'], ['c'], ['d'], ['e']], $rows);
    }

    public function test_visible_if_hides_a_section_until_its_value_is_filled(): void
    {
        $schema = new DvcSchema($this->schema([]));
        $section = ['type' => 'text', 'paragraphs' => ['x'], 'visible_if' => 'installment_2_amount'];

        $this->assertFalse((new DvcDocument($schema, ['installment_2_amount' => ''], self::CONFIG))->visible($section));
        $this->assertTrue((new DvcDocument($schema, ['installment_2_amount' => '80'], self::CONFIG))->visible($section));
        $this->assertTrue((new DvcDocument($schema, [], self::CONFIG))->visible(['type' => 'broker']));
    }
}
