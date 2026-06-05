<?php

namespace App\Modules\Platform\Services\WhatsApp;

use App\Modules\Platform\Contracts\WhatsAppGateway;
use App\Modules\Platform\Models\WhatsAppMessage;
use App\Modules\Platform\Services\SettingsManager;
use Illuminate\Database\Eloquent\Model;

/**
 * Orchestrates WhatsApp delivery: resolves per-company configuration (settings
 * shadow the global config), persists a {@see WhatsAppMessage} log row for every
 * attempt, and delegates the actual send to the configured {@see WhatsAppGateway}.
 *
 * Drivers never touch the database — all logging happens here.
 */
class WhatsAppService
{
    public const SETTINGS_MODULE = 'whatsapp';

    public const SETTING_ENABLED = 'enabled';

    public const SETTING_TOKEN = 'token';

    public const SETTING_SENDER = 'sender';

    public const SETTING_RECEIPT_TEMPLATE = 'receipt_template';

    public function __construct(
        private readonly WhatsAppGateway $gateway,
        private readonly SettingsManager $settings,
    ) {}

    /**
     * Whether WhatsApp sending is active for a company.
     *
     * Requires BOTH the global kill-switch (config) AND the per-company toggle
     * to be on. When no per-company toggle exists, the global flag wins.
     */
    public function isEnabled(int $companyId): bool
    {
        if (! (bool) config('services.whatsapp.enabled')) {
            return false;
        }

        $companyFlag = $this->settings->get(
            $companyId,
            self::SETTINGS_MODULE,
            self::SETTING_ENABLED,
            true, // default to enabled once the global switch is on
        );

        return (bool) $companyFlag;
    }

    /**
     * Per-company gateway credentials, falling back to the global config.
     *
     * @return array{token: string|null, sender: string|null}
     */
    public function credentialsFor(int $companyId): array
    {
        return [
            'token' => $this->settings->get(
                $companyId,
                self::SETTINGS_MODULE,
                self::SETTING_TOKEN,
                config('services.whatsapp.fonnte.token'),
            ),
            'sender' => $this->settings->get(
                $companyId,
                self::SETTINGS_MODULE,
                self::SETTING_SENDER,
                config('services.whatsapp.fonnte.sender'),
            ),
        ];
    }

    /**
     * Send a message and record the outcome.
     *
     * Always returns a persisted {@see WhatsAppMessage}. When sending is
     * disabled the row is stored with status "skipped" and no network call is
     * made.
     */
    public function send(int $companyId, string $to, string $body, ?Model $related = null, ?int $userId = null): WhatsAppMessage
    {
        $message = new WhatsAppMessage([
            'company_id' => $companyId,
            'to' => $to,
            'body' => $body,
            'status' => WhatsAppMessage::STATUS_PENDING,
            'created_by' => $userId,
        ]);

        if ($related !== null) {
            $message->related()->associate($related);
        }

        if (! $this->isEnabled($companyId)) {
            $message->status = WhatsAppMessage::STATUS_SKIPPED;
            $message->error = 'WhatsApp disabled for company.';
            $message->save();

            return $message;
        }

        $result = $this->gateway->send($to, $body, $this->credentialsFor($companyId));

        $message->provider = $result->provider;
        $message->provider_message_id = $result->providerMessageId;
        $message->status = $result->ok
            ? WhatsAppMessage::STATUS_SENT
            : WhatsAppMessage::STATUS_FAILED;
        $message->error = $result->error;
        $message->save();

        return $message;
    }
}
