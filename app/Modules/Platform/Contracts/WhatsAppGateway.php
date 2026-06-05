<?php

namespace App\Modules\Platform\Contracts;

use App\Modules\Platform\Support\WhatsAppSendResult;

/**
 * A WhatsApp delivery driver.
 *
 * Implementations MUST be side-effect free with respect to persistence: they
 * send (or simulate sending) a message and report the outcome. Logging the
 * attempt to the database is the caller's responsibility (see WhatsAppService).
 */
interface WhatsAppGateway
{
    /**
     * Send a plain-text WhatsApp message.
     *
     * @param  string  $to  Recipient in international digits (e.g. 628123456789).
     * @param  array{token?: string|null, sender?: string|null}  $credentials  Per-company credentials.
     */
    public function send(string $to, string $body, array $credentials = []): WhatsAppSendResult;

    /**
     * Short provider identifier persisted with each message (e.g. "fonnte", "log").
     */
    public function name(): string;
}
