<?php

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Models\Payment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListPayments
{
    /**
     * List payments with optional filters and pagination.
     *
     * @param  array{partner_id?: int, payment_type?: string, status?: string, start_date?: string, end_date?: string, per_page?: int}  $filters
     */
    public function execute(int $companyId, array $filters = []): LengthAwarePaginator
    {
        $query = Payment::query()
            ->forCompany($companyId)
            ->with(['allocations', 'partner'])
            ->orderByDesc('payment_date')
            ->orderByDesc('id');

        if (isset($filters['partner_id'])) {
            $query->where('partner_id', $filters['partner_id']);
        }

        if (isset($filters['payment_type'])) {
            $query->where('payment_type', $filters['payment_type']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['start_date'])) {
            $query->whereDate('payment_date', '>=', $filters['start_date']);
        }

        if (isset($filters['end_date'])) {
            $query->whereDate('payment_date', '<=', $filters['end_date']);
        }

        $perPage = $filters['per_page'] ?? 15;

        return $query->paginate($perPage);
    }
}
