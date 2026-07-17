<?php

namespace App\Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Exports\ExpenseExport;
use App\Modules\Finance\Exports\IncomeExport;
use App\Modules\Finance\Exports\SpreadsheetExport;
use App\Modules\Finance\Http\Requests\ExportFinanceRequest;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinanceExportController extends Controller
{
    /**
     * Stream the income export (posted inbound payments + completed POS sales).
     */
    public function income(ExportFinanceRequest $request): StreamedResponse
    {
        [$companyId, $from, $to] = $this->context($request);

        return $this->stream(new IncomeExport($companyId, $from, $to), 'income');
    }

    /**
     * Stream the expense export (posted outbound payments).
     */
    public function expense(ExportFinanceRequest $request): StreamedResponse
    {
        [$companyId, $from, $to] = $this->context($request);

        return $this->stream(new ExpenseExport($companyId, $from, $to), 'expense');
    }

    private function stream(SpreadsheetExport $export, string $kind): StreamedResponse
    {
        $spreadsheet = $export->sheet();

        return response()->streamDownload(
            function () use ($spreadsheet): void {
                (new Xlsx($spreadsheet))->save('php://output');
            },
            $this->filename($kind),
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    /**
     * @return array{0: int, 1: string|null, 2: string|null}
     */
    private function context(ExportFinanceRequest $request): array
    {
        $validated = $request->validated();

        return [
            (int) $request->attributes->get('active_company_id'),
            $validated['from'] ?? null,
            $validated['to'] ?? null,
        ];
    }

    private function filename(string $kind): string
    {
        return $kind.'-'.now()->format('Ymd-His').'.xlsx';
    }
}
