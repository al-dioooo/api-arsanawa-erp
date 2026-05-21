<?php

namespace App\Modules\Inventory\Actions;

use App\Models\User;
use App\Modules\Inventory\Models\ProductBranchAvailability;
use App\Modules\Inventory\Models\ProductVariant;

class SetBranchAvailability
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(ProductVariant $variant, User $user, array $data): ProductBranchAvailability
    {
        return ProductBranchAvailability::updateOrCreate(
            [
                'branch_id' => $data['branch_id'],
                'product_variant_id' => $variant->id,
            ],
            [
                'company_id' => $variant->company_id,
                'is_available' => $data['is_available'] ?? true,
                'is_exclusive' => $data['is_exclusive'] ?? false,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ],
        );
    }
}
