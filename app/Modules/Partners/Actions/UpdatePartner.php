<?php

namespace App\Modules\Partners\Actions;

use App\Models\User;
use App\Modules\Partners\Models\Partner;

class UpdatePartner
{
    /**
     * @param  array{name?: string, type?: string, code?: string|null, email?: string|null, phone?: string|null, tax_identifier?: string|null, national_id?: string|null, credit_limit?: string|null, transaction_limit?: string|null, status?: string, notes?: string|null}  $data
     * @return array{partner: Partner}
     */
    public function execute(User $user, Partner $partner, array $data): array
    {
        $partner->update(array_merge($data, ['updated_by' => $user->id]));

        return ['partner' => $partner->fresh()->load('contacts', 'addresses')];
    }
}
