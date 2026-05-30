<?php

namespace App\Modules\Pos\Actions;

use App\Modules\Pos\Models\Sale;
use Illuminate\Validation\ValidationException;

class UpdateExternalCateringOrderStatus
{
    /**
     * @param  array{id: int, source_channel: string, created_by?: int|null}  $apiKey
     *
     * @throws ValidationException
     */
    public function execute(Sale $sale, array $apiKey, string $status): Sale
    {
        if ($status === 'confirmed' && $sale->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => [__('Only draft external catering orders can be confirmed.')],
            ]);
        }

        if ($status === 'void' && ! in_array($sale->status, ['draft', 'confirmed'], true)) {
            throw ValidationException::withMessages([
                'status' => [__('Only draft or confirmed external catering orders can be voided.')],
            ]);
        }

        $sale->update([
            'status' => $status,
            'updated_by' => $apiKey['created_by'] ?? null,
        ]);

        return $sale->load(['lines', 'payments', 'promotions', 'register']);
    }
}
