<?php

namespace App\Modules\Partners\Actions;

use App\Models\User;
use App\Modules\Partners\Models\Partner;
use Illuminate\Support\Facades\DB;

class CreatePartner
{
    /**
     * @param  array{name: string, type: string, code?: string|null, email?: string|null, phone?: string|null, tax_identifier?: string|null, national_id?: string|null, credit_limit?: string|null, transaction_limit?: string|null, status?: string, notes?: string|null, contacts?: array<int, array<string, mixed>>, addresses?: array<int, array<string, mixed>>}  $data
     * @return array{partner: Partner}
     */
    public function execute(User $user, int $companyId, array $data): array
    {
        return DB::transaction(function () use ($user, $companyId, $data): array {
            $partner = Partner::create([
                'company_id' => $companyId,
                'type' => $data['type'],
                'name' => $data['name'],
                'code' => $data['code'] ?? null,
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'tax_identifier' => $data['tax_identifier'] ?? null,
                'national_id' => $data['national_id'] ?? null,
                'credit_limit' => $data['credit_limit'] ?? null,
                'transaction_limit' => $data['transaction_limit'] ?? null,
                'status' => $data['status'] ?? 'active',
                'notes' => $data['notes'] ?? null,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            if (! empty($data['contacts'])) {
                foreach ($data['contacts'] as $contact) {
                    $partner->contacts()->create($contact);
                }
            }

            if (! empty($data['addresses'])) {
                foreach ($data['addresses'] as $address) {
                    $partner->addresses()->create($address);
                }
            }

            return ['partner' => $partner->load('contacts', 'addresses')];
        });
    }
}
