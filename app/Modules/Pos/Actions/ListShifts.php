<?php

namespace App\Modules\Pos\Actions;

use App\Modules\Pos\Models\CashierShift;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListShifts
{
    /**
     * @param  array{register_id?: int, status?: string, per_page?: int}  $filters
     */
    public function execute(int $companyId, array $filters = []): LengthAwarePaginator
    {
        $query = CashierShift::query()
            ->forCompany($companyId)
            ->with(['register', 'user'])
            ->orderByDesc('opened_at')
            ->orderByDesc('id');

        if (isset($filters['register_id'])) {
            $query->where('register_id', $filters['register_id']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }
}
