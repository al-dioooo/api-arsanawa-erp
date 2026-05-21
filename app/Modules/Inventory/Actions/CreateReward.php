<?php

namespace App\Modules\Inventory\Actions;

use App\Models\User;
use App\Modules\Inventory\Models\Reward;
use Illuminate\Support\Facades\DB;

class CreateReward
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(int $companyId, User $user, array $data): Reward
    {
        return DB::transaction(function () use ($companyId, $user, $data): Reward {
            $reward = Reward::create([
                'company_id' => $companyId,
                'branch_id' => $data['branch_id'] ?? null,
                'name' => $data['name'],
                'calculation_type' => $data['calculation_type'],
                'value' => $data['value'],
                'min_quantity' => $data['min_quantity'] ?? null,
                'effective_from' => $data['effective_from'],
                'effective_to' => $data['effective_to'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            if (! empty($data['targets'])) {
                foreach ($data['targets'] as $target) {
                    $reward->targets()->create($target);
                }
            }

            return $reward->load('targets');
        });
    }
}
