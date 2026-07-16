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
        $availability = ProductBranchAvailability::firstOrNew([
            'branch_id' => $data['branch_id'],
            'product_variant_id' => $variant->id,
        ]);

        // Only overwrite the flags the caller actually provided, so an
        // availability-only update cannot clobber stored exclusivity.
        if (! $availability->exists) {
            $availability->company_id = $variant->company_id;
            $availability->is_available = true;
            $availability->is_exclusive = false;
            $availability->created_by = $user->id;
        }

        if (array_key_exists('is_available', $data)) {
            $availability->is_available = (bool) $data['is_available'];
        }

        if (array_key_exists('is_exclusive', $data)) {
            $availability->is_exclusive = (bool) $data['is_exclusive'];
        }

        $availability->updated_by = $user->id;
        $availability->save();

        return $availability;
    }
}
