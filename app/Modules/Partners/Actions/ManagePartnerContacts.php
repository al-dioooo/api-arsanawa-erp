<?php

namespace App\Modules\Partners\Actions;

use App\Modules\Partners\Models\Partner;
use App\Modules\Partners\Models\PartnerContact;

class ManagePartnerContacts
{
    /**
     * @param  array{name: string, role?: string|null, email?: string|null, phone?: string|null, is_primary?: bool}  $data
     */
    public function addContact(Partner $partner, array $data): PartnerContact
    {
        return $partner->contacts()->create($data);
    }

    /**
     * @param  array{name?: string, role?: string|null, email?: string|null, phone?: string|null, is_primary?: bool}  $data
     */
    public function updateContact(PartnerContact $contact, array $data): PartnerContact
    {
        $contact->update($data);

        return $contact->fresh();
    }

    public function deleteContact(PartnerContact $contact): void
    {
        $contact->delete();
    }
}
