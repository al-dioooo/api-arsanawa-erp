<?php

namespace App\Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Exports\ExpenseExport;
use App\Modules\Finance\Exports\IncomeExport;
use App\Modules\Finance\Http\Requests\ExportFinanceRequest;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FinanceExportController extends Controller
{
    /**
     * Stream the income export (posted inbound payments + completed POS sales).
     */
    public function income(ExportFinanceRequest $request): BinaryFileResponse
    {
        [$companyId, $from, $to] = $this->context($request);

        return Excel::download(
            new IncomeExport($companyId, $from, $to),
            $this->filename('income'),
        );
    }

    /**
     * Stream the expense export (posted outbound payments).
     */
    public function expense(ExportFinanceRequest $request): BinaryFileResponse
    {
        [$companyId, $from, $to] = $this->context($request);

        return Excel::download(
            new ExpenseExport($companyId, $from, $to),
            $this->filename('expense'),
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
