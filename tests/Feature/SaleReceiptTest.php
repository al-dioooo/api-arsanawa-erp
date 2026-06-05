<?php

use App\Modules\Platform\Models\WhatsAppMessage;
use App\Modules\Platform\Services\WhatsApp\WhatsAppService;
use App\Modules\Partners\Models\Partner;
use App\Modules\Pos\Support\SaleReceiptMessage;
use App\Modules\Pos\Events\SaleImported;
use App\Modules\Pos\Jobs\SendSaleReceipt;
use App\Modules\Pos\Models\Sale;
use Database\Seeders\CurrencySeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    setPermissionsTeamId(null);
    $this->seed(CurrencySeeder::class);
    config([
        'services.whatsapp.enabled' => true,
        'services.whatsapp.driver' => 'log',
    ]);
});

/**
 * @return array{0: int, 1: int} [companyId, branchId]
 */
function receiptActor(): array
{
    [, , $companyId, $branchId] = financeActor();

    return [$companyId, $branchId];
}

function makeImportedSale(int $companyId, int $branchId, ?string $phone): Sale
{
    $partner = Partner::create([
        'company_id' => $companyId,
        'type' => 'customer',
        'name' => 'Budi',
        'code' => 'CUST-'.uniqid(),
        'phone' => $phone,
        'status' => 'active',
    ]);

    return Sale::create([
        'company_id' => $companyId,
        'branch_id' => $branchId,
        'sale_number' => 'IMP-'.uniqid(),
        'type' => 'catering',
        'partner_id' => $partner->id,
        'customer_name' => 'Budi',
        'status' => 'confirmed',
        'order_date' => '2026-06-01',
        'currency_id' => 1,
        'exchange_rate' => '1.00000000',
        'total' => '250000.0000',
    ]);
}

describe('SendSaleReceipt job', function () {
    it('sends one receipt for a sale with a phone and is idempotent', function (): void {
        [$companyId, $branchId] = receiptActor();
        $sale = makeImportedSale($companyId, $branchId, '08123456789');

        $job = new SendSaleReceipt($sale->id);
        $job->handle(app(WhatsAppService::class), app(SaleReceiptMessage::class));

        $sent = WhatsAppMessage::where('related_id', $sale->id)
            ->where('status', WhatsAppMessage::STATUS_SENT)->get();
        expect($sent)->toHaveCount(1)
            ->and($sent->first()->to)->toBe('628123456789');

        // Re-run: must not create a second sent row.
        $job->handle(app(WhatsAppService::class), app(SaleReceiptMessage::class));
        expect(WhatsAppMessage::where('related_id', $sale->id)
            ->where('status', WhatsAppMessage::STATUS_SENT)->count())->toBe(1);
    });

    it('skips a sale with no phone', function (): void {
        [$companyId, $branchId] = receiptActor();
        $sale = makeImportedSale($companyId, $branchId, null);

        (new SendSaleReceipt($sale->id))
            ->handle(app(WhatsAppService::class), app(SaleReceiptMessage::class));

        $rows = WhatsAppMessage::where('related_id', $sale->id)->get();
        expect($rows)->toHaveCount(1)
            ->and($rows->first()->status)->toBe(WhatsAppMessage::STATUS_SKIPPED);
    });

    it('skips when WhatsApp is disabled for the company', function (): void {
        config(['services.whatsapp.enabled' => false]);
        [$companyId, $branchId] = receiptActor();
        $sale = makeImportedSale($companyId, $branchId, '08123456789');

        (new SendSaleReceipt($sale->id))
            ->handle(app(WhatsAppService::class), app(SaleReceiptMessage::class));

        expect(WhatsAppMessage::where('related_id', $sale->id)->first()->status)
            ->toBe(WhatsAppMessage::STATUS_SKIPPED);
    });

    it('records a failed row when the gateway errors, without throwing', function (): void {
        config([
            'services.whatsapp.driver' => 'fonnte',
            'services.whatsapp.fonnte.base_url' => 'https://api.fonnte.com/send',
            'services.whatsapp.fonnte.token' => 'token',
        ]);
        Http::fake(['api.fonnte.com/*' => Http::response(['status' => false, 'reason' => 'server error'], 500)]);

        [$companyId, $branchId] = receiptActor();
        $sale = makeImportedSale($companyId, $branchId, '08123456789');

        (new SendSaleReceipt($sale->id))
            ->handle(app(WhatsAppService::class), app(SaleReceiptMessage::class));

        expect(WhatsAppMessage::where('related_id', $sale->id)->first()->status)
            ->toBe(WhatsAppMessage::STATUS_FAILED);
    });
});

describe('SaleImported event', function () {
    it('enqueues a receipt job', function (): void {
        Queue::fake();
        [$companyId, $branchId] = receiptActor();
        $sale = makeImportedSale($companyId, $branchId, '08123456789');

        event(new SaleImported($sale->id));

        Queue::assertPushed(SendSaleReceipt::class, fn (SendSaleReceipt $job) => $job->saleId === $sale->id);
    });
});
