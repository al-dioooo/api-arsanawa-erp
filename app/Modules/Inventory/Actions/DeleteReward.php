<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Models\Reward;

class DeleteReward
{
    public function execute(Reward $reward): void
    {
        $reward->delete();
    }
}
