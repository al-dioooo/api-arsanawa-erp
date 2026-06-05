<?php

use App\Modules\Platform\Support\PhoneNormalizer;

it('converts local 0-prefixed numbers to international form', function (): void {
    expect(PhoneNormalizer::normalize('08123456789'))->toBe('628123456789');
});

it('keeps already country-prefixed numbers', function (): void {
    expect(PhoneNormalizer::normalize('628123456789'))->toBe('628123456789');
});

it('strips spaces, dashes and plus signs', function (): void {
    expect(PhoneNormalizer::normalize('+62 812-3456-789'))->toBe('628123456789');
});

it('prepends the country code to bare national numbers', function (): void {
    expect(PhoneNormalizer::normalize('8123456789'))->toBe('628123456789');
});

it('returns null for empty or digitless input', function (): void {
    expect(PhoneNormalizer::normalize(null))->toBeNull();
    expect(PhoneNormalizer::normalize(''))->toBeNull();
    expect(PhoneNormalizer::normalize('   '))->toBeNull();
    expect(PhoneNormalizer::normalize('abc'))->toBeNull();
});
