<?php

namespace App\Modules\Platform\Services\WhatsApp;

use App\Modules\Platform\Contracts\WhatsAppGateway;
use App\Modules\Platform\Support\WhatsAppSendResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Fonnte WhatsApp gateway (https://fonnte.com).
 *
 * Posts to the Fonnte send endpoint with the device token in the Authorization
 * header. Never throws on a delivery failure — any HTTP/transport error is
 * mapped into a failed {@see WhatsAppSendResult}.
 */
class FonnteDriver implements WhatsAppGateway
{
    public const PROVIDER = 'fonnte';

    public function __construct(
        private readonly string $baseUrl,
        private readonly ?string $defaultToken = null,
        private readonly ?string $defaultSender = null,
        private readonly int $timeoutSeconds = 15,
    ) {}

    public function name(): string
    {
        return self::PROVIDER;
    }

    public function send(string $to, string $body, array $credentials = []): WhatsAppSendResult
    {
        $token = $credentials['token'] ?? $this->defaultToken;
        $sender = $credentials['sender'] ?? $this->defaultSender;

        if (empty($token)) {
            return WhatsAppSendResult::failed(self::PROVIDER, 'Missing Fonnte token.');
        }

        $payload = ['target' => $to, 'message' => $body];

        if (! empty($sender)) {
            $payload['countryCode'] = '0'; // Fonnte: target is already fully qualified.
        }

        try {
            $response = Http::asForm()
                ->timeout($this->timeoutSeconds)
                ->withHeaders(['Authorization' => $token])
                ->post($this->baseUrl, $payload);
        } catch (ConnectionException $e) {
            return WhatsAppSendResult::failed(self::PROVIDER, 'Connection error: '.$e->getMessage());
        }

        $json = $response->json() ?? [];

        // Fonnte returns HTTP 200 with {"status": true|false, "id": [...], "reason": "..."}.
        $status = $json['status'] ?? null;
        $ok = $response->successful() && ($status === true || $status === 'true' || $status === 1);

        if (! $ok) {
            $reason = $json['reason']
                ?? $json['message']
                ?? ('HTTP '.$response->status());

            return WhatsAppSendResult::failed(self::PROVIDER, (string) $reason, $json);
        }

        $id = $json['id'] ?? null;

        if (is_array($id)) {
            $id = $id[0] ?? null;
        }

        return WhatsAppSendResult::ok(self::PROVIDER, $id !== null ? (string) $id : null, $json);
    }
}
