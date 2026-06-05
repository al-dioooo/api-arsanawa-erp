<?php

namespace App\Modules\Platform\Support;

/**
 * Normalises Indonesian phone numbers to the international digit form expected
 * by WhatsApp gateways (e.g. "628123456789"), without a leading "+".
 */
final class PhoneNormalizer
{
    private const DEFAULT_COUNTRY_CODE = '62';

    /**
     * @return string|null Normalised digits, or null when the input has no usable digits.
     */
    public static function normalize(?string $raw, string $countryCode = self::DEFAULT_COUNTRY_CODE): ?string
    {
        if ($raw === null) {
            return null;
        }

        // Keep digits only (drops "+", spaces, dashes, parentheses).
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        if ($digits === '') {
            return null;
        }

        // Local form "08xxxx" -> "628xxxx".
        if (str_starts_with($digits, '0')) {
            return $countryCode.substr($digits, 1);
        }

        // Already country-prefixed.
        if (str_starts_with($digits, $countryCode)) {
            return $digits;
        }

        // Bare national number (e.g. "8123...") -> prepend country code.
        return $countryCode.$digits;
    }
}
