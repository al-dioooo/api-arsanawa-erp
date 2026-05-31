<?php

namespace App\Modules\Platform\Services;

class CateringMode
{
    public function __construct(private readonly SettingsManager $settings) {}

    public function posCateringOnly(int $companyId): bool
    {
        return (bool) $this->settings->get($companyId, 'pos', 'catering_only', false);
    }

    public function inventoryRestricted(int $companyId): bool
    {
        return $this->posCateringOnly($companyId)
            || (bool) $this->settings->get($companyId, 'inventory', 'hide_catering_restricted_features', false);
    }
}
