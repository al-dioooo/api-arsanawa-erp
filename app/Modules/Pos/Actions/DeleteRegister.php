<?php

namespace App\Modules\Pos\Actions;

use App\Modules\Pos\Models\Register;
use Illuminate\Validation\ValidationException;

class DeleteRegister
{
    /**
     * @throws ValidationException
     */
    public function execute(Register $register): void
    {
        if ($register->shifts()->exists()) {
            throw ValidationException::withMessages([
                'register' => [__('Registers with cashier shifts cannot be deleted.')],
            ]);
        }

        $register->delete();
    }
}
