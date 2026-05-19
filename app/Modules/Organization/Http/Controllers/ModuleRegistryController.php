<?php

namespace App\Modules\Organization\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Organization\Actions\GetEnabledModules;
use App\Modules\Organization\Http\Resources\CompanyResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ModuleRegistryController extends Controller
{
    public function index(Request $request, GetEnabledModules $action): JsonResponse
    {
        $result = $action->execute(
            $request->user(),
            $request->attributes->get('active_company_id'),
        );

        return $this->success(
            [
                'enabled' => $result['enabled'],
                'available' => $result['available'],
                'company' => $result['company'] ? new CompanyResource($result['company']) : null,
            ],
            __('Modules retrieved.'),
        );
    }
}
