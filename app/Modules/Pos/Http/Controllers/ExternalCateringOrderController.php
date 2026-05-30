<?php

namespace App\Modules\Pos\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Pos\Actions\CreateExternalCateringOrder;
use App\Modules\Pos\Actions\UpdateExternalCateringOrderStatus;
use App\Modules\Pos\Http\Requests\StoreExternalCateringOrderRequest;
use App\Modules\Pos\Http\Requests\UpdateExternalCateringOrderStatusRequest;
use App\Modules\Pos\Http\Resources\SaleResource;
use App\Modules\Pos\Models\Sale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExternalCateringOrderController extends Controller
{
    public function store(StoreExternalCateringOrderRequest $request, CreateExternalCateringOrder $action): JsonResponse
    {
        $result = $action->execute(
            (int) $request->attributes->get('active_company_id'),
            $this->apiKeyContext($request),
            $request->validated(),
        );

        return $this->success(
            [
                'sale' => new SaleResource($result['sale']),
                'idempotent' => ! $result['created'],
            ],
            $result['created'] ? __('External catering order created.') : __('External catering order retrieved.'),
            $result['created'] ? 201 : 200,
        );
    }

    public function show(Request $request, string $externalReference): JsonResponse
    {
        return $this->success(
            ['sale' => new SaleResource($this->resolveSale($request, $externalReference))],
            __('External catering order retrieved.'),
        );
    }

    public function updateStatus(
        UpdateExternalCateringOrderStatusRequest $request,
        UpdateExternalCateringOrderStatus $action,
        string $externalReference,
    ): JsonResponse {
        $sale = $action->execute(
            $this->resolveSale($request, $externalReference),
            $this->apiKeyContext($request),
            $request->validated('status'),
        );

        return $this->success(
            ['sale' => new SaleResource($sale)],
            __('External catering order status updated.'),
        );
    }

    private function resolveSale(Request $request, string $externalReference): Sale
    {
        $apiKey = $this->apiKeyContext($request);

        return Sale::query()
            ->forCompany((int) $request->attributes->get('active_company_id'))
            ->where('source', 'external')
            ->where('source_channel', $apiKey['source_channel'])
            ->where('external_reference', $externalReference)
            ->firstOrFail()
            ->load(['lines', 'payments', 'promotions', 'register']);
    }

    /**
     * @return array{id: int, source_channel: string, created_by: int|null}
     */
    private function apiKeyContext(Request $request): array
    {
        $apiKey = $request->attributes->get('external_api_key');

        return [
            'id' => (int) $apiKey->id,
            'source_channel' => $apiKey->source_channel,
            'created_by' => $apiKey->created_by,
        ];
    }
}
