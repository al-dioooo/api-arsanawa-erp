<?php

namespace App\Modules\Platform\Support;

/**
 * Immutable result of a single WhatsApp send attempt.
 *
 * Drivers never throw on a delivery failure; they return a result with
 * {@see $ok} = false and a captured {@see $error} instead.
 */
final readonly class WhatsAppSendResult
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public bool $ok,
        public string $provider,
        public ?string $providerMessageId = null,
        public ?string $error = null,
        public array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $raw
     */
    public static function ok(string $provider, ?string $providerMessageId = null, array $raw = []): self
    {
        return new self(true, $provider, $providerMessageId, null, $raw);
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    public static function failed(string $provider, string $error, array $raw = []): self
    {
        return new self(false, $provider, null, $error, $raw);
    }
}
