<?php

namespace App\Modules\Pos\Actions;

use App\Modules\Pos\Models\Sale;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListSales
{
    /**
     * @param  array{type?: string, status?: string, branch_id?: int, fulfilment_from?: string, fulfilment_to?: string, per_page?: int}  $filters
     */
    public function execute(int $companyId, array $filters = []): LengthAwarePaginator
    {
        $query = Sale::query()
            ->forCompany($companyId)
            ->with(['lines', 'payments', 'register'])
            ->orderByDesc('order_date')
            ->orderByDesc('id');

        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['branch_id'])) {
            $query->where('branch_id', $filters['branch_id']);
        }

        if (isset($filters['fulfilment_from'])) {
            $query->where('fulfilment_date', '>=', $filters['fulfilment_from']);
        }

        if (isset($filters['fulfilment_to'])) {
            $query->where('fulfilment_date', '<=', $filters['fulfilment_to']);
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }
}
