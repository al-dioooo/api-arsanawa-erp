<?php

namespace App\Modules\Pos\Actions;

use App\Models\User;
use App\Modules\Pos\Models\Sale;
use Illuminate\Validation\ValidationException;

class ConfirmOrder
{
    /**
     * @throws ValidationException
     */
    public function execute(Sale $sale, User $user): Sale
    {
        if ($sale->type !== 'catering') {
            throw ValidationException::withMessages([
                'sale' => [__('Only catering orders can be confirmed.')],
            ]);
        }

        if ($sale->status !== 'draft') {
            throw ValidationException::withMessages([
                'sale' => [__('Only draft catering orders can be confirmed.')],
            ]);
        }

        if ($sale->partner_id === null || $sale->fulfilment_date === null) {
            throw ValidationException::withMessages([
                'sale' => [__('Catering orders require a partner and fulfilment date before confirmation.')],
            ]);
        }

        $sale->update([
            'status' => 'confirmed',
            'updated_by' => $user->id,
        ]);

        return $sale->load(['lines', 'payments', 'promotions', 'register']);
    }
}
