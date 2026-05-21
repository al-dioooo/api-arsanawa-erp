<?php

namespace App\Modules\Partners\Actions;

use App\Modules\Partners\Models\Partner;
use Illuminate\Pagination\LengthAwarePaginator;

class ListPartners
{
    /**
     * @param  array{type?: string, status?: string, search?: string, per_page?: int}  $filters
     * @return LengthAwarePaginator<Partner>
     */
    public function execute(int $companyId, array $filters = []): LengthAwarePaginator
    {
        $query = Partner::forCompany($companyId)->with('contacts', 'addresses');

        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['search']) && $filters['search'] !== '') {
            $search = $filters['search'];
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $query->orderBy('name');

        return $query->paginate($filters['per_page'] ?? 15);
    }
}
