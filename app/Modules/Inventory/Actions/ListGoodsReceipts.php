<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Models\GoodsReceipt;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListGoodsReceipts
{
    /**
     * List goods-receipt document headers with optional filters and pagination.
     *
     * @param  array{partner_id?: int, branch_id?: int, status?: string, start_date?: string, end_date?: string, per_page?: int}  $filters
     */
    public function execute(int $companyId, array $filters = []): LengthAwarePaginator
    {
        $query = GoodsReceipt::query()
            ->forCompany($companyId)
            ->with('partner')
            ->withCount('lines')
            ->orderByDesc('receipt_date')
            ->orderByDesc('id');

        if (isset($filters['partner_id'])) {
            $query->where('partner_id', (int) $filters['partner_id']);
        }

        if (isset($filters['branch_id'])) {
            $query->where('branch_id', (int) $filters['branch_id']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['start_date'])) {
            $query->whereDate('receipt_date', '>=', $filters['start_date']);
        }

        if (isset($filters['end_date'])) {
            $query->whereDate('receipt_date', '<=', $filters['end_date']);
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }
}
