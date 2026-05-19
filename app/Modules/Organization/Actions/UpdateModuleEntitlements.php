<?php

namespace App\Modules\Organization\Actions;

use App\Models\User;
use App\Modules\Organization\Models\Company;
use App\Modules\Organization\Models\ModuleEntitlement;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class UpdateModuleEntitlements
{
    /**
     * @param  array{modules: list<array{module: string, is_enabled: bool, expires_at?: string|null}>}  $data
     * @return Collection<int, ModuleEntitlement>
     */
    public function execute(Company $company, User $user, array $data): Collection
    {
        return DB::transaction(function () use ($company, $user, $data): Collection {
            foreach ($data['modules'] as $moduleData) {
                $enabled = (bool) $moduleData['is_enabled'];

                ModuleEntitlement::updateOrCreate(
                    [
                        'company_id' => $company->id,
                        'module' => $moduleData['module'],
                    ],
                    [
                        'is_enabled' => $enabled,
                        'enabled_at' => $enabled ? now() : null,
                        'expires_at' => $moduleData['expires_at'] ?? null,
                        'updated_by' => $user->id,
                        'created_by' => $user->id,
                    ],
                );
            }

            return $company->moduleEntitlements()
                ->orderBy('module')
                ->get();
        });
    }
}
