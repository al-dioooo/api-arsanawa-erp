<?php

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Models\Invoice;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListInvoices
{
    /**
     * List customer invoices with optional filters and pagination.
     *
     * @param  array{partner_id?: int, status?: string, per_page?: int}  $filters
     */
    public function execute(int $companyId, array $filters = []): LengthAwarePaginator
    {
        $query = Invoice::query()
            ->forCompany($companyId)
            ->with(['lines', 'partner'])
            ->orderByDesc('invoice_date')
            ->orderByDesc('id');

        if (isset($filters['partner_id'])) {
            $query->where('partner_id', $filters['partner_id']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $perPage = $filters['per_page'] ?? 15;

        return $query->paginate($perPage);
    }
}
