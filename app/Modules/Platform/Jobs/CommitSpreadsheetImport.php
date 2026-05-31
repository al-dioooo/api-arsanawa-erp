<?php

namespace App\Modules\Platform\Jobs;

use App\Modules\Platform\Models\ImportBatch;
use App\Modules\Platform\Services\InventoryProductImportProcessor;
use App\Modules\Platform\Services\PosCateringImportProcessor;
use App\Modules\Platform\Services\SpreadsheetImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

class CommitSpreadsheetImport implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(private readonly int $importBatchId) {}

    public function handle(
        PosCateringImportProcessor $posProcessor,
        InventoryProductImportProcessor $inventoryProcessor,
    ): void {
        $import = ImportBatch::query()->with('rows')->findOrFail($this->importBatchId);

        if ($import->status !== 'queued') {
            return;
        }

        $import->forceFill([
            'status' => 'processing',
            'failure_message' => null,
        ])->save();

        try {
            $result = DB::transaction(function () use ($import, $posProcessor, $inventoryProcessor): array {
                $rows = $import->rows
                    ->sortBy('row_number')
                    ->pluck('normalized')
                    ->values();

                return $import->kind === SpreadsheetImportService::POS_KIND
                    ? $posProcessor->commit($import->company_id, $import->user_id, $rows)
                    : $inventoryProcessor->commit($import->company_id, $import->user_id, $rows);
            });

            $import->forceFill([
                'status' => 'completed',
                'created_count' => $result['created'],
                'updated_count' => $result['updated'],
                'committed_at' => now(),
            ])->save();
        } catch (Throwable $throwable) {
            $import->forceFill([
                'status' => 'failed',
                'failure_message' => $throwable->getMessage(),
            ])->save();

            throw $throwable;
        }
    }
}
