<?php

namespace App\Modules\Inventory\Actions;

use App\Models\User;
use App\Modules\Inventory\Models\PriceList;

class CreatePriceList
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(int $companyId, User $user, array $data): PriceList
    {
        return PriceList::create([
            'company_id' => $companyId,
            'name' => $data['name'],
            'currency_id' => $data['currency_id'] ?? null,
            'branch_id' => $data['branch_id'] ?? null,
            'is_default' => $data['is_default'] ?? false,
            'is_active' => $data['is_active'] ?? true,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
    }
}
