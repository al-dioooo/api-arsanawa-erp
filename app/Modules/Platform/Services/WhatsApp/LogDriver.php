<?php

namespace App\Modules\Platform\Services\WhatsApp;

use App\Modules\Platform\Contracts\WhatsAppGateway;
use App\Modules\Platform\Support\WhatsAppSendResult;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Local / test WhatsApp gateway.
 *
 * Writes the rendered message to the application log and reports success,
 * without performing any network I/O. Selected when
 * config('services.whatsapp.driver') === 'log'.
 */
class LogDriver implements WhatsAppGateway
{
    public const PROVIDER = 'log';

    public function name(): string
    {
        return self::PROVIDER;
    }

    public function send(string $to, string $body, array $credentials = []): WhatsAppSendResult
    {
        $id = (string) Str::uuid();

        Log::info('whatsapp.outbound', [
            'provider' => self::PROVIDER,
            'to' => $to,
            'body' => $body,
            'message_id' => $id,
        ]);

        return WhatsAppSendResult::ok(self::PROVIDER, $id, ['to' => $to, 'body' => $body]);
    }
}
