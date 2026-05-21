<?php

namespace App\Modules\Organization\Support;

final class BuiltinRoles
{
    /**
     * Roles seeded automatically for every company; they cannot be edited or deleted.
     */
    public const NAMES = ['company-owner', 'company-admin'];

    public static function isBuiltin(string $name): bool
    {
        return in_array($name, self::NAMES, true);
    }
}
