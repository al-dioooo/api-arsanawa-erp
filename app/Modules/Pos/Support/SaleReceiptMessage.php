<?php

namespace App\Modules\Pos\Support;

use App\Modules\Organization\Models\Company;
use App\Modules\Platform\Services\SettingsManager;
use App\Modules\Platform\Services\WhatsApp\WhatsAppService;
use App\Modules\Pos\Models\Sale;

/**
 * Builds the WhatsApp receipt body for an imported sale from the company's
 * configurable template (falling back to a sensible Indonesian default).
 *
 * Supported placeholders: {customer} {sale_number} {total} {date} {company}
 */
class SaleReceiptMessage
{
    public const DEFAULT_TEMPLATE =
        'Halo {customer}, terima kasih telah memesan di {company}. '
        .'Pesanan {sale_number} senilai {total} tertanggal {date} telah kami terima. '
        .'Sampai jumpa!';

    public function __construct(private readonly SettingsManager $settings) {}

    public function build(Sale $sale): string
    {
        $template = $this->settings->get(
            $sale->company_id,
            WhatsAppService::SETTINGS_MODULE,
            WhatsAppService::SETTING_RECEIPT_TEMPLATE,
            self::DEFAULT_TEMPLATE,
        );

        if (! is_string($template) || trim($template) === '') {
            $template = self::DEFAULT_TEMPLATE;
        }

        return strtr($template, [
            '{customer}' => (string) ($sale->customer_name ?: ($sale->partner?->name ?? 'Pelanggan')),
            '{sale_number}' => (string) $sale->sale_number,
            '{total}' => $this->formatIdr($sale->total),
            '{date}' => optional($sale->order_date)->format('d/m/Y') ?? '',
            '{company}' => $this->companyName($sale->company_id),
        ]);
    }

    private function formatIdr(mixed $amount): string
    {
        return 'Rp '.number_format((float) $amount, 0, ',', '.');
    }

    private function companyName(int $companyId): string
    {
        return (string) (Company::query()->whereKey($companyId)->value('name') ?? '');
    }
}
