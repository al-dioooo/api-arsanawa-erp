<?php

namespace App\Modules\Platform\Actions;

use App\Modules\Platform\Models\Setting;
use App\Modules\Platform\Services\SettingsManager;
use Illuminate\Support\Collection;

class ListSettings
{
    public function __construct(private readonly SettingsManager $settings) {}

    /**
     * @return Collection<int, Setting>
     */
    public function execute(int $companyId, ?string $module = null): Collection
    {
        return $this->settings->all($companyId, $module);
    }
}
