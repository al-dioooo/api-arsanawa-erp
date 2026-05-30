<?php

namespace App\Modules\Partners\Actions;

use App\Modules\Partners\Models\Partner;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FindOrCreateCustomer
{
    /**
     * @param  array{name: string, email?: string|null, phone?: string|null}  $data
     * @return array{id: int, name: string}
     */
    public function execute(int $companyId, ?int $userId, array $data): array
    {
        return DB::transaction(function () use ($companyId, $userId, $data): array {
            $email = isset($data['email']) && is_string($data['email']) ? Str::lower($data['email']) : null;
            $phone = $data['phone'] ?? null;

            $partner = null;

            if ($email !== null || $phone !== null) {
                $partner = Partner::query()
                    ->forCompany($companyId)
                    ->customers()
                    ->where(function ($query) use ($email, $phone): void {
                        if ($email !== null) {
                            $query->orWhere('email', $email);
                        }

                        if ($phone !== null) {
                            $query->orWhere('phone', $phone);
                        }
                    })
                    ->first();
            }

            if (! $partner) {
                $partner = Partner::query()->create([
                    'company_id' => $companyId,
                    'type' => 'customer',
                    'name' => $data['name'],
                    'email' => $email,
                    'phone' => $phone,
                    'status' => 'active',
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);

                $partner->contacts()->create([
                    'name' => $data['name'],
                    'email' => $email,
                    'phone' => $phone,
                    'is_primary' => true,
                ]);
            }

            return [
                'id' => $partner->id,
                'name' => $partner->name,
            ];
        });
    }
}
