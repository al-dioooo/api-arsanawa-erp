<?php

namespace App\Modules\Inventory\Actions;

use App\Models\User;
use App\Modules\Inventory\Models\Discount;
use Illuminate\Support\Facades\DB;

class CreateDiscount
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(int $companyId, User $user, array $data): Discount
    {
        return DB::transaction(function () use ($companyId, $user, $data): Discount {
            $discount = Discount::create([
                'company_id' => $companyId,
                'branch_id' => $data['branch_id'] ?? null,
                'name' => $data['name'],
                'calculation_type' => $data['calculation_type'],
                'value' => $data['value'],
                'min_quantity' => $data['min_quantity'] ?? null,
                'starting_item_number' => $data['starting_item_number'] ?? null,
                'multiply' => $data['multiply'] ?? false,
                'effective_from' => $data['effective_from'],
                'effective_to' => $data['effective_to'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            if (! empty($data['targets'])) {
                foreach ($data['targets'] as $target) {
                    $discount->targets()->create($target);
                }
            }

            if (! empty($data['dependencies'])) {
                foreach ($data['dependencies'] as $dep) {
                    $discount->dependencies()->create($dep);
                }
            }

            if (! empty($data['giveaways'])) {
                foreach ($data['giveaways'] as $giveaway) {
                    $discount->giveaways()->create($giveaway);
                }
            }

            return $discount->load(['targets', 'dependencies', 'giveaways']);
        });
    }
}
