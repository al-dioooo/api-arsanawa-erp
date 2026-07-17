<?php

namespace App\Modules\Organization\Support;

/**
 * Immutable, cross-module view of a company.
 *
 * Modules outside Organization read this instead of the Company model, so a
 * schema change stays behind the Organization boundary.
 */
final readonly class CompanyProfile
{
    public function __construct(
        public int $id,
        public string $name,
        public string $slug,
    ) {}
}
