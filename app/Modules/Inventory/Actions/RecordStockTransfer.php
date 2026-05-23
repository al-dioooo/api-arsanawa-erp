<?php

namespace App\Modules\Inventory\Actions;

use App\Models\User;
use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Inventory\Services\StockService;
use Illuminate\Validation\ValidationException;

class RecordStockTransfer
{
    public function __construct(
        private readonly StockService $stockService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function execute(int $companyId, User $user, array $data): StockTransfer
    {
        return $this->stockService->recordTransfer([
            'company_id' => $companyId,
            'from_branch_id' => $data['from_branch_id'],
            'to_branch_id' => $data['to_branch_id'],
            'items' => $data['items'],
            'notes' => $data['notes'] ?? null,
            'created_by' => $user->id,
        ]);
    }
}
