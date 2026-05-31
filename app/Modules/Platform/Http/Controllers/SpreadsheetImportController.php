<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Http\Requests\InspectSpreadsheetImportRequest;
use App\Modules\Platform\Http\Requests\PreviewSpreadsheetImportRequest;
use App\Modules\Platform\Http\Resources\ImportBatchResource;
use App\Modules\Platform\Http\Resources\ImportRowResource;
use App\Modules\Platform\Models\ImportBatch;
use App\Modules\Platform\Services\SpreadsheetImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SpreadsheetImportController extends Controller
{
    public function posTemplate(SpreadsheetImportService $service, string $format): Response
    {
        return $service->templateResponse(SpreadsheetImportService::POS_KIND, $format);
    }

    public function inventoryTemplate(SpreadsheetImportService $service, string $format): Response
    {
        return $service->templateResponse(SpreadsheetImportService::INVENTORY_KIND, $format);
    }

    public function posInspect(InspectSpreadsheetImportRequest $request, SpreadsheetImportService $service): JsonResponse
    {
        return $this->inspect($request, $service, SpreadsheetImportService::POS_KIND);
    }

    public function posConfiguredPreview(Request $request, SpreadsheetImportService $service): JsonResponse
    {
        $import = $service->previewConfiguredPosCateringImport(
            (int) $request->attributes->get('active_company_id'),
            $request->user(),
        );

        return $this->success(
            [
                'import' => new ImportBatchResource($import),
                'rows' => ImportRowResource::collection($import->rows),
                'sheets' => $import->sheets ?? [],
            ],
            __('Configured catering import previewed.'),
        );
    }

    public function inventoryInspect(InspectSpreadsheetImportRequest $request, SpreadsheetImportService $service): JsonResponse
    {
        return $this->inspect($request, $service, SpreadsheetImportService::INVENTORY_KIND);
    }

    public function posShow(Request $request, int $import): JsonResponse
    {
        return $this->showImport($request, SpreadsheetImportService::POS_KIND, $import);
    }

    public function inventoryShow(Request $request, int $import): JsonResponse
    {
        return $this->showImport($request, SpreadsheetImportService::INVENTORY_KIND, $import);
    }

    public function posPreview(PreviewSpreadsheetImportRequest $request, SpreadsheetImportService $service, int $import): JsonResponse
    {
        return $this->preview($request, $service, SpreadsheetImportService::POS_KIND, $import);
    }

    public function inventoryPreview(PreviewSpreadsheetImportRequest $request, SpreadsheetImportService $service, int $import): JsonResponse
    {
        return $this->preview($request, $service, SpreadsheetImportService::INVENTORY_KIND, $import);
    }

    public function posCommit(Request $request, SpreadsheetImportService $service, int $import): JsonResponse
    {
        return $this->commit($request, $service, SpreadsheetImportService::POS_KIND, $import);
    }

    public function inventoryCommit(Request $request, SpreadsheetImportService $service, int $import): JsonResponse
    {
        return $this->commit($request, $service, SpreadsheetImportService::INVENTORY_KIND, $import);
    }

    private function inspect(InspectSpreadsheetImportRequest $request, SpreadsheetImportService $service, string $kind): JsonResponse
    {
        $import = $service->inspect(
            $kind,
            (int) $request->attributes->get('active_company_id'),
            $request->user(),
            $request->file('file'),
            $request->validated('source_url'),
        );

        return $this->success(
            [
                'import' => new ImportBatchResource($import),
                'sheets' => $import->sheets ?? [],
            ],
            __('Spreadsheet import inspected.'),
            201,
        );
    }

    private function preview(PreviewSpreadsheetImportRequest $request, SpreadsheetImportService $service, string $kind, int $id): JsonResponse
    {
        $import = $service->preview(
            $this->resolveImport($request, $kind, $id),
            $request->validated('sheet_name'),
        );

        return $this->success(
            [
                'import' => new ImportBatchResource($import),
                'rows' => ImportRowResource::collection($import->rows),
            ],
            __('Spreadsheet import previewed.'),
        );
    }

    private function commit(Request $request, SpreadsheetImportService $service, string $kind, int $id): JsonResponse
    {
        $import = $service->commit($this->resolveImport($request, $kind, $id));

        return $this->success(
            [
                'import' => new ImportBatchResource($import),
                'rows' => ImportRowResource::collection($import->rows),
            ],
            __('Spreadsheet import queued.'),
            202,
        );
    }

    private function showImport(Request $request, string $kind, int $id): JsonResponse
    {
        $import = $this->resolveImport($request, $kind, $id)->load('rows');

        return $this->success(
            [
                'import' => new ImportBatchResource($import),
                'rows' => ImportRowResource::collection($import->rows),
            ],
            __('Spreadsheet import retrieved.'),
        );
    }

    private function resolveImport(Request $request, string $kind, int $id): ImportBatch
    {
        return ImportBatch::query()
            ->forCompany((int) $request->attributes->get('active_company_id'))
            ->where('kind', $kind)
            ->findOrFail($id);
    }
}
