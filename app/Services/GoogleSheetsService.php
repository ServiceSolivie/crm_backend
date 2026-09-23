<?php

namespace App\Services;

use Google\Client;
use Google\Service\Sheets;

class GoogleSheetsService
{
    protected Sheets $sheets;

    protected string $spreadsheetId;

    public function __construct()
    {
        $client = new Client();
        $client->setAuthConfig(config('services.google.credentials_path'));
        $client->addScope(Sheets::SPREADSHEETS_READONLY);

        $this->sheets = new Sheets($client);
        $this->spreadsheetId = config('services.google.sheet_id');
    }

    public function getRows(string $sheetName, int $startRow = 1, ?int $endRow = null): array
    {
        if ($endRow) {
            $range = "'{$sheetName}'!A{$startRow}:ZZ{$endRow}";
        } else {
            $range = "'{$sheetName}'!A{$startRow}:ZZ";
        }

        $response = $this->sheets->spreadsheets_values->get($this->spreadsheetId, $range);

        return $response->getValues() ?? [];
    }

}
