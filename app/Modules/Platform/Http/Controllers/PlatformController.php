<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Actions\ListCurrencies;
use App\Modules\Platform\Actions\ListSettings;
use App\Modules\Platform\Actions\UpsertSettings;
use App\Modules\Platform\Http\Requests\ListSettingsRequest;
use App\Modules\Platform\Http\Requests\UpsertSettingsRequest;
use App\Modules\Platform\Http\Resources\CurrencyResource;
use App\Modules\Platform\Http\Resources\SettingResource;
use Illuminate\Http\JsonResponse;

class PlatformController extends Controller
{
    public function currencies(ListCurrencies $action): JsonResponse
    {
        return $this->success(
            ['currencies' => CurrencyResource::collection($action->execute())],
            __('Currencies retrieved.'),
        );
    }

    public function settings(ListSettingsRequest $request, ListSettings $action): JsonResponse
    {
        $companyId = (int) $request->attributes->get('active_company_id');

        $settings = $action->execute($companyId, $request->validated('module'));

        return $this->success(
            ['settings' => SettingResource::collection($settings)],
            __('Settings retrieved.'),
        );
    }

    public function updateSettings(UpsertSettingsRequest $request, UpsertSettings $action): JsonResponse
    {
        $companyId = (int) $request->attributes->get('active_company_id');

        $settings = $action->execute($companyId, $request->user(), $request->validated('settings'));

        return $this->success(
            ['settings' => SettingResource::collection(collect($settings))],
            __('Settings updated.'),
        );
    }
}
