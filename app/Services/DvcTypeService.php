<?php

namespace App\Services;

use App\Enums\RoleEnum;
use App\Exceptions\ApiException;
use App\Models\Contract;
use App\Models\DvcType;
use App\Models\DvcTypeVersion;
use App\Models\User;
use App\Support\Dvc\DvcSchema;
use App\Support\Dvc\DvcSchemaValidator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * DVC types managed by the super admin: one per insurance type, a JSON
 * schema per type, every schema change published as a new version.
 */
class DvcTypeService
{
    public function __construct(
        protected DvcSchemaValidator $validator,
        protected DvcPdfRenderer $renderer,
    ) {}

    /**
     * The super admin sees every type; everyone else only the active ones
     * (the types an agent can pick on a lead).
     */
    public function list(User $user): Collection
    {
        return DvcType::query()
            ->with(['currentVersion.creator:id,name'])
            ->withCount('versions')
            ->unless($user->hasRole(RoleEnum::SUPER_ADMIN->value), fn ($query) => $query->where('is_active', true))
            ->orderBy('insurance_type')
            ->get();
    }

    public function show(DvcType $dvcType): DvcType
    {
        return $dvcType->load(['currentVersion.creator:id,name', 'versions' => fn ($query) => $query->with('creator:id,name')->withCount('contracts')]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes, User $creator): DvcType
    {
        $this->assertValidSchema($attributes['schema']);

        return DB::transaction(function () use ($attributes, $creator) {
            $dvcType = DvcType::query()->create([
                'insurance_type' => $attributes['insurance_type'],
                'label' => $attributes['label'],
                'filename_prefix' => $attributes['filename_prefix'] ?? 'DVC',
                'is_active' => $attributes['is_active'] ?? true,
                'created_by' => $creator->id,
            ]);

            $this->createVersion($dvcType, $attributes['schema'], $attributes['change_note'] ?? null, $creator);

            // createVersion() set current_version_id on a fresh copy of the row
            return $this->show($dvcType->refresh());
        });
    }

    /**
     * Label, filename prefix and active flag. The insurance type never
     * changes, and the schema changes only by publishing a version.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(DvcType $dvcType, array $attributes): DvcType
    {
        $dvcType->update(array_intersect_key($attributes, array_flip(['label', 'filename_prefix', 'is_active'])));

        return $this->show($dvcType);
    }

    /**
     * Save the schema as the type's next version, used by every DVC generated
     * from now on. DVCs already generated keep the version they were made with.
     *
     * @param  array<string, mixed>  $schema
     */
    public function publish(DvcType $dvcType, array $schema, ?string $changeNote, User $user): DvcType
    {
        $this->assertValidSchema($schema);

        if ($dvcType->currentVersion && $dvcType->currentVersion->schema == $schema) {
            throw new ApiException('Le schéma est identique à la version en cours : rien à publier.', 422);
        }

        DB::transaction(fn () => $this->createVersion($dvcType, $schema, $changeNote, $user));

        return $this->show($dvcType->refresh());
    }

    public function delete(DvcType $dvcType): bool
    {
        $used = Contract::query()
            ->whereIn('dvc_type_version_id', $dvcType->versions()->select('id'))
            ->exists();

        if ($used) {
            throw new ApiException('Des DVC ont déjà été générés avec ce type : désactivez-le au lieu de le supprimer.', 409);
        }

        return DB::transaction(function () use ($dvcType) {
            $dvcType->update(['current_version_id' => null]);

            return (bool) $dvcType->delete();
        });
    }

    /**
     * @throws ApiException 422 with the errors keyed by their path in the schema
     */
    public function assertValidSchema(mixed $schema): void
    {
        $errors = $this->validator->validate($schema);

        if ($errors !== []) {
            throw new ApiException('Le schéma contient des erreurs.', 422, $errors);
        }
    }

    /**
     * A PDF of the schema filled with example values, before publishing it.
     *
     * @param  array<string, mixed>  $schema
     */
    public function preview(array $schema): string
    {
        $this->assertValidSchema($schema);

        $dvcSchema = new DvcSchema($schema);

        return $this->renderer->render($dvcSchema, $this->renderer->sampleData($dvcSchema), 'APERCU', 1, now());
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    protected function createVersion(DvcType $dvcType, array $schema, ?string $changeNote, User $user): DvcTypeVersion
    {
        // Lock the type so two publishes cannot take the same number
        $dvcType = DvcType::query()->lockForUpdate()->findOrFail($dvcType->id);

        $version = $dvcType->versions()->create([
            'version' => (int) $dvcType->versions()->max('version') + 1,
            'schema' => $schema,
            'change_note' => $changeNote,
            'created_by' => $user->id,
        ]);

        $dvcType->update(['current_version_id' => $version->id]);

        return $version;
    }
}
