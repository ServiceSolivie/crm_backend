<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A user's work (the leads he created, his notes, histories, appointments,
 * payments, documents, imports, contracts) used to be deleted with him: on
 * 24/11/2026 deleting one super admin wiped 32,000 leads. The database now
 * refuses to delete a user who still has any of it — he is deactivated
 * instead. Pivot rows (sources, vault credentials) and daily stats still go
 * with the user.
 */
return new class extends Migration
{
    /** table => user columns that were "cascade on delete" */
    private const COLUMNS = [
        'leads' => ['created_by'],
        'lead_notes' => ['user_id'],
        'lead_status_histories' => ['changed_by'],
        'lead_assignment_histories' => ['to_user_id', 'assigned_by'],
        'appointments' => ['agent_id', 'created_by'],
        'payments' => ['created_by'],
        'lead_documents' => ['uploaded_by'],
        'lead_imports' => ['imported_by'],
        'contracts' => ['generated_by'],
    ];

    public function up(): void
    {
        $this->rebuild(fn ($foreign) => $foreign->restrictOnDelete());
    }

    public function down(): void
    {
        $this->rebuild(fn ($foreign) => $foreign->cascadeOnDelete());
    }

    private function rebuild(Closure $onDelete): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            // Only the foreign keys that exist are replaced (a copied database may have none)
            $existing = collect(Schema::getForeignKeys($table))
                ->filter(fn (array $fk) => $fk['foreign_table'] === 'users' && count($fk['columns']) === 1)
                ->keyBy(fn (array $fk) => $fk['columns'][0]);
            $columns = array_values(array_filter($columns, fn (string $column) => $existing->has($column)));

            if ($columns === []) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($columns, $existing, $onDelete) {
                foreach ($columns as $column) {
                    $blueprint->dropForeign($existing[$column]['name']);
                    $onDelete($blueprint->foreign($column)->references('id')->on('users'));
                }
            });
        }
    }
};
