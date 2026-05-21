<?php

namespace App\Modules\Platform\Actions;

use App\Models\User;
use App\Modules\Platform\Models\Setting;
use App\Modules\Platform\Services\SettingsManager;
use Illuminate\Support\Facades\DB;

class UpsertSettings
{
    public function __construct(private readonly SettingsManager $settings) {}

    /**
     * @param  array<int, array{module: string, key: string, value: mixed, branch_id?: int|null}>  $settings
     * @return array<int, Setting>
     */
    public function execute(int $companyId, User $user, array $settings): array
    {
        return DB::transaction(function () use ($companyId, $user, $settings): array {
            $result = [];

            foreach ($settings as $row) {
                $result[] = $this->settings->set(
                    $companyId,
                    $row['module'],
                    $row['key'],
                    $row['value'] ?? null,
                    $row['branch_id'] ?? null,
                    $user->id,
                );
            }

            return $result;
        });
    }
}
