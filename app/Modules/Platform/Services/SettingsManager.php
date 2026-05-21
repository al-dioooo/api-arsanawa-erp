<?php

namespace App\Modules\Platform\Services;

use App\Modules\Platform\Models\Setting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class SettingsManager
{
    /**
     * Resolve a setting value. A branch-level row shadows the company-level row.
     */
    public function get(int $companyId, string $module, string $key, mixed $default = null, ?int $branchId = null): mixed
    {
        $rows = $this->rows($companyId, $module);

        if ($branchId !== null) {
            $branchRow = $rows->first(
                fn (Setting $row): bool => $row->branch_id === $branchId && $row->key === $key,
            );

            if ($branchRow !== null) {
                return $branchRow->value;
            }
        }

        $companyRow = $rows->first(
            fn (Setting $row): bool => $row->branch_id === null && $row->key === $key,
        );

        return $companyRow !== null ? $companyRow->value : $default;
    }

    /**
     * Create or update a setting, then bust the module cache.
     */
    public function set(int $companyId, string $module, string $key, mixed $value, ?int $branchId = null, ?int $userId = null): Setting
    {
        $setting = Setting::firstOrNew([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'module' => $module,
            'key' => $key,
        ]);

        if (! $setting->exists) {
            $setting->created_by = $userId;
        }

        $setting->value = $value;
        $setting->updated_by = $userId;
        $setting->save();

        $this->forget($companyId, $module);

        return $setting;
    }

    /**
     * @return Collection<int, Setting>
     */
    public function all(int $companyId, ?string $module = null): Collection
    {
        if ($module !== null) {
            return $this->rows($companyId, $module);
        }

        return Setting::query()->where('company_id', $companyId)->get();
    }

    /**
     * @return Collection<int, Setting>
     */
    private function rows(int $companyId, string $module): Collection
    {
        return Cache::rememberForever(
            $this->cacheKey($companyId, $module),
            fn (): Collection => Setting::query()
                ->where('company_id', $companyId)
                ->where('module', $module)
                ->get(),
        );
    }

    private function forget(int $companyId, string $module): void
    {
        Cache::forget($this->cacheKey($companyId, $module));
    }

    private function cacheKey(int $companyId, string $module): string
    {
        return "platform.settings.{$companyId}.{$module}";
    }
}
