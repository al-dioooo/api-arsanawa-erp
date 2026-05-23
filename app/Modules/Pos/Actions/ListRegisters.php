<?php

namespace App\Modules\Pos\Actions;

use App\Modules\Pos\Models\Register;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListRegisters
{
    /**
     * @param  array{branch_id?: int, is_active?: bool, per_page?: int}  $filters
     */
    public function execute(int $companyId, array $filters = []): LengthAwarePaginator
    {
        $query = Register::query()
            ->forCompany($companyId)
            ->with(['branch', 'cashAccount'])
            ->orderBy('code');

        if (isset($filters['branch_id'])) {
            $query->where('branch_id', $filters['branch_id']);
        }

        if (array_key_exists('is_active', $filters)) {
            $query->where('is_active', $filters['is_active']);
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }
}
