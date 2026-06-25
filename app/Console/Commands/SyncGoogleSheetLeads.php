<?php

namespace App\Console\Commands;

use App\Services\GoogleSheetLeadImporter;
use App\Services\GoogleSheetsService;
use Illuminate\Console\Command;

class SyncGoogleSheetLeads extends Command
{
    protected $signature = 'google:sync-leads
        {--sheet= : Specific sheet tab to sync (Lead, Decennale, Qualite)}
        {--date= : Only import rows matching this date (Y-m-d format)}
        {--from-row= : Start from a specific row number (overrides cursor)}
        {--fresh : Ignore cursor, re-process from row 2}';

    protected $description = 'Import leads from Google Sheets into the CRM';

    protected const SUPPORTED_SHEETS = ['Lead', 'Decennale', 'Qualite'];

    public function handle(GoogleSheetLeadImporter $importer, GoogleSheetsService $sheetsService): int
    {
        $sheetOption = $this->option('sheet');
        $dateFilter = $this->option('date');
        $fromRow = $this->option('from-row') ? (int) $this->option('from-row') : null;
        $fresh = $this->option('fresh');

        if ($fresh) {
            $fromRow = 2;
        }

        $sheets = $sheetOption
            ? [trim($sheetOption)]
            : self::SUPPORTED_SHEETS;

        foreach ($sheets as $sheet) {
            if (! in_array($sheet, self::SUPPORTED_SHEETS, true)) {
                $this->error("Unknown sheet: {$sheet}. Supported: " . implode(', ', self::SUPPORTED_SHEETS));

                continue;
            }

            $this->info("Syncing sheet: {$sheet}...");

            $log = $importer->import($sheet, $dateFilter, $fromRow);

            $this->table(
                ['Sheet', 'Total', 'Imported', 'Skipped', 'Failed', 'Last Row'],
                [[$log->sheet_name, $log->total_rows, $log->imported, $log->skipped, $log->failed, $log->last_row_synced]]
            );

            if ($log->error_details) {
                $this->warn("Errors ({$log->failed}):");
                foreach (array_slice($log->error_details, 0, 10) as $err) {
                    $this->line("  Row {$err['row']}: {$err['error']}");
                }
                if ($log->failed > 10) {
                    $this->line("  ... and " . ($log->failed - 10) . " more errors");
                }
            }
        }

        $this->info('Done.');

        return self::SUCCESS;
    }
}
