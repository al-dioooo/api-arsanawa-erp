<?php

namespace App\Modules\Pos\Actions;

use App\Models\User;
use App\Modules\Pos\Models\Sale;
use Illuminate\Validation\ValidationException;

class CancelSale
{
    /**
     * @throws ValidationException
     */
    public function execute(Sale $sale, User $user): Sale
    {
        if (! in_array($sale->status, ['draft', 'confirmed'], true)) {
            throw ValidationException::withMessages([
                'sale' => [__('Only draft or confirmed sales can be canceled. Completed sales must be voided.')],
            ]);
        }

        $sale->update([
            'status' => 'void',
            'updated_by' => $user->id,
        ]);

        return $sale->load(['lines', 'payments', 'promotions', 'register']);
    }
}
