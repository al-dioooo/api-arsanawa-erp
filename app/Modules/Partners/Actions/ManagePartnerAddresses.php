<?php

namespace App\Modules\Partners\Actions;

use App\Modules\Partners\Models\Partner;
use App\Modules\Partners\Models\PartnerAddress;

/**
 * Deliberate exception to the one-execute()-per-Action convention: partner addresses
 * are a child collection whose add/update/delete share invariants and always
 * ship together, so the lifecycle lives in one class instead of three files.
 */
class ManagePartnerAddresses
{
    /**
     * @param  array{type?: string, label?: string|null, address_line_1: string, address_line_2?: string|null, city?: string|null, province?: string|null, postal_code?: string|null, country?: string, is_default?: bool}  $data
     */
    public function addAddress(Partner $partner, array $data): PartnerAddress
    {
        return $partner->addresses()->create($data);
    }

    /**
     * @param  array{type?: string, label?: string|null, address_line_1?: string, address_line_2?: string|null, city?: string|null, province?: string|null, postal_code?: string|null, country?: string, is_default?: bool}  $data
     */
    public function updateAddress(PartnerAddress $address, array $data): PartnerAddress
    {
        $address->update($data);

        return $address->fresh();
    }

    public function deleteAddress(PartnerAddress $address): void
    {
        $address->delete();
    }
}
