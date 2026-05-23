<?php

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Models\JournalEntry;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListJournalEntries
{
    public function execute(int $companyId, array $filters = []): LengthAwarePaginator
    {
        $query = JournalEntry::query()
            ->forCompany($companyId)
            ->with(['lines.account', 'period'])
            ->orderBy('entry_date', 'desc')
            ->orderBy('id', 'desc');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }
}
