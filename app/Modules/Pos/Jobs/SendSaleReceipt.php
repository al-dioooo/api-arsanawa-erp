<?php

namespace App\Modules\Pos\Jobs;

use App\Modules\Platform\Services\WhatsApp\WhatsAppService;
use App\Modules\Pos\Models\Sale;
use App\Modules\Pos\Support\SaleReceiptMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Sends a single WhatsApp receipt for an imported sale.
 *
 * Resilient by design: a missing phone or disabled WhatsApp is recorded as a
 * "skipped" log row, gateway failures are recorded as "failed" (never thrown
 * past the retry budget), and the job is idempotent — it will not send twice
 * for the same sale.
 */
class SendSaleReceipt implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public readonly int $saleId,
        public readonly ?int $userId = null,
    ) {}

    public function handle(WhatsAppService $whatsApp, SaleReceiptMessage $formatter): void
    {
        $sale = Sale::query()->with('partner')->find($this->saleId);

        if ($sale === null) {
            return;
        }

        // Idempotency: never send a second receipt for the same sale.
        if ($whatsApp->hasSentFor($sale)) {
            return;
        }

        $phone = $sale->recipientPhone();

        if ($phone === null) {
            $whatsApp->skip(
                companyId: $sale->company_id,
                reason: 'No recipient phone number on the sale.',
                related: $sale,
                userId: $this->userId,
            );

            return;
        }

        $whatsApp->send(
            companyId: $sale->company_id,
            to: $phone,
            body: $formatter->build($sale),
            related: $sale,
            userId: $this->userId,
        );
    }
}
