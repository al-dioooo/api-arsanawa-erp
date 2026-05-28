<?php

namespace App\Modules\Pos\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Pos\Actions\AddSalePayment;
use App\Modules\Pos\Actions\ApplyPromotions;
use App\Modules\Pos\Actions\CancelSale;
use App\Modules\Pos\Actions\CloseShift;
use App\Modules\Pos\Actions\CompleteSale;
use App\Modules\Pos\Actions\ConfirmOrder;
use App\Modules\Pos\Actions\CreateRegister;
use App\Modules\Pos\Actions\CreateSale;
use App\Modules\Pos\Actions\DeleteRegister;
use App\Modules\Pos\Actions\GetCurrentShift;
use App\Modules\Pos\Actions\GetSalesReport;
use App\Modules\Pos\Actions\GetShiftReport;
use App\Modules\Pos\Actions\ListRegisters;
use App\Modules\Pos\Actions\ListSales;
use App\Modules\Pos\Actions\ListShifts;
use App\Modules\Pos\Actions\OpenShift;
use App\Modules\Pos\Actions\RemoveSalePayment;
use App\Modules\Pos\Actions\UpdateRegister;
use App\Modules\Pos\Actions\UpdateSale;
use App\Modules\Pos\Actions\VoidSale;
use App\Modules\Pos\Http\Requests\AddSalePaymentRequest;
use App\Modules\Pos\Http\Requests\ApplyPromotionsRequest;
use App\Modules\Pos\Http\Requests\CancelSaleRequest;
use App\Modules\Pos\Http\Requests\CloseShiftRequest;
use App\Modules\Pos\Http\Requests\CompleteSaleRequest;
use App\Modules\Pos\Http\Requests\ConfirmOrderRequest;
use App\Modules\Pos\Http\Requests\GetCurrentShiftRequest;
use App\Modules\Pos\Http\Requests\GetSalesReportRequest;
use App\Modules\Pos\Http\Requests\GetShiftReportRequest;
use App\Modules\Pos\Http\Requests\ListRegistersRequest;
use App\Modules\Pos\Http\Requests\ListSalesRequest;
use App\Modules\Pos\Http\Requests\ListShiftsRequest;
use App\Modules\Pos\Http\Requests\OpenShiftRequest;
use App\Modules\Pos\Http\Requests\RemoveSalePaymentRequest;
use App\Modules\Pos\Http\Requests\StoreRegisterRequest;
use App\Modules\Pos\Http\Requests\StoreSaleRequest;
use App\Modules\Pos\Http\Requests\UpdateRegisterRequest;
use App\Modules\Pos\Http\Requests\UpdateSaleRequest;
use App\Modules\Pos\Http\Requests\VoidSaleRequest;
use App\Modules\Pos\Http\Resources\RegisterResource;
use App\Modules\Pos\Http\Resources\SaleResource;
use App\Modules\Pos\Http\Resources\ShiftResource;
use App\Modules\Pos\Models\CashierShift;
use App\Modules\Pos\Models\Register;
use App\Modules\Pos\Models\Sale;
use App\Modules\Pos\Models\SalePayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PosController extends Controller
{
    public function sales(ListSalesRequest $request, ListSales $action): JsonResponse
    {
        $companyId = (int) $request->attributes->get('active_company_id');
        $sales = $action->execute($companyId, $request->validated());

        return $this->success(
            [
                'sales' => SaleResource::collection($sales->items()),
                'pagination' => [
                    'current_page' => $sales->currentPage(),
                    'last_page' => $sales->lastPage(),
                    'per_page' => $sales->perPage(),
                    'total' => $sales->total(),
                ],
            ],
            __('Sales retrieved.'),
        );
    }

    public function storeSale(StoreSaleRequest $request, CreateSale $action): JsonResponse
    {
        $companyId = (int) $request->attributes->get('active_company_id');
        $sale = $action->execute($companyId, $request->user(), $request->validated());

        return $this->success(
            ['sale' => new SaleResource($sale)],
            __('Sale created.'),
            201,
        );
    }

    public function showSale(ListSalesRequest $request, int $sale): JsonResponse
    {
        return $this->success(
            ['sale' => new SaleResource($this->resolveSale($request, $sale)->load(['lines', 'payments', 'register']))],
            __('Sale retrieved.'),
        );
    }

    public function updateSale(UpdateSaleRequest $request, UpdateSale $action, int $sale): JsonResponse
    {
        $updated = $action->execute($this->resolveSale($request, $sale), $request->user(), $request->validated());

        return $this->success(
            ['sale' => new SaleResource($updated)],
            __('Sale updated.'),
        );
    }

    public function addSalePayment(AddSalePaymentRequest $request, AddSalePayment $action, int $sale): JsonResponse
    {
        $updated = $action->execute($this->resolveSale($request, $sale), $request->user(), $request->validated());

        return $this->success(
            ['sale' => new SaleResource($updated)],
            __('Payment recorded.'),
            201,
        );
    }

    public function applyPromotions(ApplyPromotionsRequest $request, ApplyPromotions $action, int $sale): JsonResponse
    {
        $updated = $action->execute($this->resolveSale($request, $sale), $request->user());

        return $this->success(
            ['sale' => new SaleResource($updated)],
            __('Promotions applied.'),
        );
    }

    public function completeSale(CompleteSaleRequest $request, CompleteSale $action, int $sale): JsonResponse
    {
        $completed = $action->execute($this->resolveSale($request, $sale), $request->user());

        return $this->success(
            ['sale' => new SaleResource($completed)],
            __('Sale completed.'),
        );
    }

    public function confirmOrder(ConfirmOrderRequest $request, ConfirmOrder $action, int $sale): JsonResponse
    {
        $confirmed = $action->execute($this->resolveSale($request, $sale), $request->user());

        return $this->success(
            ['sale' => new SaleResource($confirmed)],
            __('Catering order confirmed.'),
        );
    }

    public function voidSale(VoidSaleRequest $request, VoidSale $action, int $sale): JsonResponse
    {
        $voided = $action->execute($this->resolveSale($request, $sale), $request->user());

        return $this->success(
            ['sale' => new SaleResource($voided)],
            __('Sale voided.'),
        );
    }

    public function cancelSale(CancelSaleRequest $request, CancelSale $action, int $sale): JsonResponse
    {
        $canceled = $action->execute($this->resolveSale($request, $sale), $request->user());

        return $this->success(
            ['sale' => new SaleResource($canceled)],
            __('Sale canceled.'),
        );
    }

    public function salesReport(GetSalesReportRequest $request, GetSalesReport $action): JsonResponse
    {
        $companyId = (int) $request->attributes->get('active_company_id');

        return $this->success(
            $action->execute($companyId, $request->validated()),
            __('Sales report retrieved.'),
        );
    }

    public function shiftReport(GetShiftReportRequest $request, GetShiftReport $action, int $shift): JsonResponse
    {
        return $this->success(
            $action->execute($this->resolveShift($request, $shift)),
            __('Shift report retrieved.'),
        );
    }

    public function removeSalePayment(RemoveSalePaymentRequest $request, RemoveSalePayment $action, int $sale, int $payment): JsonResponse
    {
        $resolvedSale = $this->resolveSale($request, $sale);
        $resolvedPayment = SalePayment::query()
            ->where('sale_id', $resolvedSale->id)
            ->findOrFail($payment);

        $updated = $action->execute($resolvedSale, $resolvedPayment, $request->user());

        return $this->success(
            ['sale' => new SaleResource($updated)],
            __('Payment removed.'),
        );
    }

    public function registers(ListRegistersRequest $request, ListRegisters $action): JsonResponse
    {
        $companyId = (int) $request->attributes->get('active_company_id');
        $registers = $action->execute($companyId, $request->validated());

        return $this->success(
            [
                'registers' => RegisterResource::collection($registers->items()),
                'pagination' => [
                    'current_page' => $registers->currentPage(),
                    'last_page' => $registers->lastPage(),
                    'per_page' => $registers->perPage(),
                    'total' => $registers->total(),
                ],
            ],
            __('Registers retrieved.'),
        );
    }

    public function storeRegister(StoreRegisterRequest $request, CreateRegister $action): JsonResponse
    {
        $companyId = (int) $request->attributes->get('active_company_id');
        $register = $action->execute($companyId, $request->user(), $request->validated());

        return $this->success(
            ['register' => new RegisterResource($register)],
            __('Register created.'),
            201,
        );
    }

    public function showRegister(ListRegistersRequest $request, int $register): JsonResponse
    {
        return $this->success(
            ['register' => new RegisterResource($this->resolveRegister($request, $register))],
            __('Register retrieved.'),
        );
    }

    public function updateRegister(UpdateRegisterRequest $request, UpdateRegister $action, int $register): JsonResponse
    {
        $updated = $action->execute(
            $this->resolveRegister($request, $register),
            $request->user(),
            $request->validated(),
        );

        return $this->success(
            ['register' => new RegisterResource($updated)],
            __('Register updated.'),
        );
    }

    public function destroyRegister(ListRegistersRequest $request, DeleteRegister $action, int $register): JsonResponse
    {
        $action->execute($this->resolveRegister($request, $register));

        return $this->success(null, __('Register deleted.'));
    }

    public function shifts(ListShiftsRequest $request, ListShifts $action): JsonResponse
    {
        $companyId = (int) $request->attributes->get('active_company_id');
        $shifts = $action->execute($companyId, $request->validated());

        return $this->success(
            [
                'shifts' => ShiftResource::collection($shifts->items()),
                'pagination' => [
                    'current_page' => $shifts->currentPage(),
                    'last_page' => $shifts->lastPage(),
                    'per_page' => $shifts->perPage(),
                    'total' => $shifts->total(),
                ],
            ],
            __('Shifts retrieved.'),
        );
    }

    public function openShift(OpenShiftRequest $request, OpenShift $action): JsonResponse
    {
        $companyId = (int) $request->attributes->get('active_company_id');
        $shift = $action->execute($companyId, $request->user(), $request->validated());

        return $this->success(
            ['shift' => new ShiftResource($shift)],
            __('Shift opened.'),
            201,
        );
    }

    public function currentShift(GetCurrentShiftRequest $request, GetCurrentShift $action): JsonResponse
    {
        $companyId = (int) $request->attributes->get('active_company_id');
        $shift = $action->execute($companyId, $request->integer('register_id') ?: null);

        return $this->success(
            ['shift' => $shift ? new ShiftResource($shift) : null],
            __('Current shift retrieved.'),
        );
    }

    public function closeShift(CloseShiftRequest $request, CloseShift $action, int $shift): JsonResponse
    {
        $closed = $action->execute($this->resolveShift($request, $shift), $request->user(), $request->validated());

        return $this->success(
            ['shift' => new ShiftResource($closed)],
            __('Shift closed.'),
        );
    }

    private function resolveRegister(Request $request, int $id): Register
    {
        return Register::query()
            ->forCompany((int) $request->attributes->get('active_company_id'))
            ->findOrFail($id);
    }

    private function resolveShift(Request $request, int $id): CashierShift
    {
        return CashierShift::query()
            ->forCompany((int) $request->attributes->get('active_company_id'))
            ->findOrFail($id);
    }

    private function resolveSale(Request $request, int $id): Sale
    {
        return Sale::query()
            ->forCompany((int) $request->attributes->get('active_company_id'))
            ->findOrFail($id);
    }
}
