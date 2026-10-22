<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\LeadExport\ImportedLeadsExportRequest;
use App\Services\ImportedLeadsExportService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Imported leads of a week / month / range with their current status:
 * GET /lead-exports/imported/summary for the screen,
 * GET /lead-exports/imported/download for the Excel file.
 * Super admin only (checked by the request).
 */
class ImportedLeadsExportController extends Controller
{
    public function __construct(protected ImportedLeadsExportService $exports) {}

    public function summary(ImportedLeadsExportRequest $request): JsonResponse
    {
        $range = $this->range($request);
        $query = $this->exports->query($range, $request->origin());

        return $this->success([
            'period' => $range['period'],
            'from' => $range['from']->toDateString(),
            'to' => $range['to']->toDateString(),
            'label' => $range['label'],
            'file_name' => $range['file_name'],
            'origin' => $request->origin(),
            ...$this->exports->summary($query),
            'preview' => $this->exports->preview($query),
        ]);
    }

    public function download(ImportedLeadsExportRequest $request): BinaryFileResponse
    {
        $range = $this->range($request);
        $query = $this->exports->query($range, $request->origin());
        $path = tempnam(sys_get_temp_dir(), 'leads-export-');

        $written = $this->exports->write($query, $range, $request->origin(), $this->exports->summary($query), $request->user(), $path);

        Log::info('Imported leads export downloaded', [
            'user_id' => $request->user()->id,
            'period' => $range['label'],
            'origin' => $request->origin(),
            'leads' => $written,
        ]);

        return response()
            ->download($path, $range['file_name'], ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
            ->deleteFileAfterSend();
    }

    /**
     * @return array{period: string, from: CarbonImmutable, to: CarbonImmutable, label: string, file_name: string}
     */
    protected function range(ImportedLeadsExportRequest $request): array
    {
        return $this->exports->range(
            $request->validated('period'),
            $request->validated('date'),
            $request->validated('from'),
            $request->validated('to'),
        );
    }
}
