<?php

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Models\ApprovalRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListApprovalRequests
{
    /**
     * List approval requests with optional filters and pagination.
     *
     * @param  array{status?: string, approvable_type?: string, per_page?: int}  $filters
     */
    public function execute(int $companyId, array $filters = []): LengthAwarePaginator
    {
        $query = ApprovalRequest::query()
            ->forCompany($companyId)
            ->with(['approvable', 'actions.user'])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['approvable_type'])) {
            $query->where('approvable_type', $filters['approvable_type']);
        }

        $perPage = $filters['per_page'] ?? 15;

        return $query->paginate($perPage);
    }
}
