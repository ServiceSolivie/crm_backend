<?php

namespace App\Support;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

/**
 * Normalises phone numbers to E.164 ("+33612345678") so that numbers typed
 * in any format ("06 12 34 56 78", "+33 6 12…", "0033…") can be compared,
 * e.g. to match a Ringover call to a lead.
 */
class PhoneNumber
{
    /**
     * Convert a user-entered number to E.164, or null when it cannot be parsed.
     */
    public static function toE164(?string $number, ?string $region = null): ?string
    {
        if ($number === null || trim($number) === '') {
            return null;
        }

        $region ??= config('services.phone.default_region', 'FR');
        $util = PhoneNumberUtil::getInstance();

        try {
            $parsed = $util->parse(self::clean($number), $region);
        } catch (NumberParseException) {
            return null;
        }

        if (! $util->isPossibleNumber($parsed)) {
            return null;
        }

        return $util->format($parsed, PhoneNumberFormat::E164);
    }

    /**
     * Ringover sends numbers as international digits without "+" (e.g. 33612345678).
     */
    public static function fromRingover(int|string|null $number): ?string
    {
        if ($number === null || $number === '') {
            return null;
        }

        $digits = preg_replace('/\D/', '', (string) $number);

        return $digits === '' ? null : self::toE164('+'.$digits);
    }

    /**
     * Ringover expects numbers as international digits without "+".
     */
    public static function toRingover(?string $e164): ?string
    {
        return $e164 ? ltrim($e164, '+') : null;
    }

    protected static function clean(string $number): string
    {
        $number = trim($number);

        // "0033 6…" → "+33 6…"
        if (str_starts_with($number, '00')) {
            $number = '+'.substr($number, 2);
        }

        return $number;
    }
}
