<?php

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Models\Bill;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListBills
{
    /**
     * List vendor bills with optional filters and pagination.
     *
     * @param  array{partner_id?: int, status?: string, start_date?: string, end_date?: string, per_page?: int}  $filters
     */
    public function execute(int $companyId, array $filters = []): LengthAwarePaginator
    {
        $query = Bill::query()
            ->forCompany($companyId)
            ->with(['lines', 'partner'])
            ->orderByDesc('bill_date')
            ->orderByDesc('id');

        if (isset($filters['partner_id'])) {
            $query->where('partner_id', $filters['partner_id']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['start_date'])) {
            $query->whereDate('bill_date', '>=', $filters['start_date']);
        }

        if (isset($filters['end_date'])) {
            $query->whereDate('bill_date', '<=', $filters['end_date']);
        }

        $perPage = $filters['per_page'] ?? 15;

        return $query->paginate($perPage);
    }
}
