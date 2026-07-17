<?php

namespace App\Modules\Platform\Support;

use App\Modules\Platform\Services\WhatsApp\WhatsAppService;

/**
 * Registry of settings that hold credentials.
 *
 * These are write-only: the API accepts a new value but never echoes the stored
 * one back to a client.
 *
 * The registry lives in code rather than as an `is_secret` column because
 * UpsertSettingsRequest validates `module` and `key` as free-form strings — a
 * database flag would be settable by whoever writes the row, which is exactly
 * the party it is meant to constrain.
 */
class SecretSettings
{
    /**
     * Secret settings, keyed as "module.key".
     *
     * @var array<int, string>
     */
    private const KEYS = [
        WhatsAppService::SETTINGS_MODULE.'.'.WhatsAppService::SETTING_TOKEN,
    ];

    public static function is(?string $module, ?string $key): bool
    {
        if ($module === null || $key === null) {
            return false;
        }

        return in_array($module.'.'.$key, self::KEYS, true);
    }
}
