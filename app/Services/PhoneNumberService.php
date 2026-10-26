<?php

namespace App\Services;

use App\Models\Setting;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumber;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberType;
use libphonenumber\PhoneNumberUtil;

/**
 * Country-aware phone number parsing/formatting on top of libphonenumber.
 *
 * Numbers are stored in E.164 (+79001234567). Input can be in any local or
 * international notation ("8 (900) 123-45-67", "+7 900 1234567", ...); the
 * country decides how a number without a leading "+" is read.
 */
class PhoneNumberService
{
    /**
     * Fallback when neither the record nor the settings carry a country.
     */
    public const FALLBACK_COUNTRY = 'RU';

    public static function util(): PhoneNumberUtil
    {
        return PhoneNumberUtil::getInstance();
    }

    /**
     * The default region for numbers entered without a country: the SIM
     * settings value, otherwise FALLBACK_COUNTRY.
     */
    public static function defaultCountry(): string
    {
        $country = self::cleanCountry(Setting::getSettings()?->sim_default_country);

        return $country ?? self::FALLBACK_COUNTRY;
    }

    /**
     * Normalize a free-form country value (ISO code from the country picker)
     * to an ISO 3166-1 alpha-2 region libphonenumber knows, or null.
     */
    public static function cleanCountry(?string $country): ?string
    {
        if (($country === null) || ($country === '')) {
            return null;
        }

        $country = strtoupper(trim($country));

        return in_array($country, self::util()->getSupportedRegions(), true) ? $country : null;
    }

    /**
     * Parse a number for the given region. Returns null when it can't be
     * parsed at all; validity is checked separately with isValid().
     */
    public static function parse(?string $raw, ?string $country = null): ?PhoneNumber
    {
        if (($raw === null) || (trim($raw) === '')) {
            return null;
        }

        try {
            return self::util()->parse(trim($raw), self::cleanCountry($country) ?? self::defaultCountry());
        } catch (NumberParseException $e) {
            return null;
        }
    }

    public static function isValid(?string $raw, ?string $country = null): bool
    {
        $number = self::parse($raw, $country);

        return $number !== null && self::util()->isValidNumber($number);
    }

    /**
     * Whether the number is a mobile (or "fixed line or mobile", which some
     * countries can't tell apart).
     */
    public static function isMobile(?string $raw, ?string $country = null): bool
    {
        $number = self::parse($raw, $country);

        if (! $number) {
            return false;
        }

        return in_array(self::util()->getNumberType($number), [PhoneNumberType::MOBILE, PhoneNumberType::FIXED_LINE_OR_MOBILE], true);
    }

    /**
     * E.164 form of a valid number, or null.
     */
    public static function toE164(?string $raw, ?string $country = null): ?string
    {
        $number = self::parse($raw, $country);

        if (! $number || ! self::util()->isValidNumber($number)) {
            return null;
        }

        return self::util()->format($number, PhoneNumberFormat::E164);
    }

    /**
     * Human-readable form. Numbers from the given country are shown in
     * national notation when $national is true, otherwise international.
     */
    public static function format(?string $e164, bool $national = false): ?string
    {
        $number = self::parse($e164);

        if (! $number) {
            return $e164;
        }

        return self::util()->format($number, $national ? PhoneNumberFormat::NATIONAL : PhoneNumberFormat::INTERNATIONAL);
    }

    /**
     * ISO region a number belongs to (e.g. "RU" for +7 9xx).
     */
    public static function regionOf(?string $e164): ?string
    {
        $number = self::parse($e164);

        return $number ? self::util()->getRegionCodeForNumber($number) : null;
    }

    /**
     * Example number for a country, used as an input placeholder.
     */
    public static function example(?string $country = null): ?string
    {
        $number = self::util()->getExampleNumberForType(self::cleanCountry($country) ?? self::defaultCountry(), PhoneNumberType::MOBILE);

        return $number ? self::util()->format($number, PhoneNumberFormat::INTERNATIONAL) : null;
    }

    /**
     * Digits only (no +, spaces, dashes). Used for format-tolerant search.
     */
    public static function digits(?string $raw): string
    {
        return preg_replace('/\D+/', '', (string) $raw) ?? '';
    }

    /**
     * Search needles for matching a stored E.164 MSISDN against free-form input
     * ("+375 29 242-67-85", "375292426785", "8 029 …", etc.).
     *
     * @return list<string> digit strings of length >= 5
     */
    public static function searchNeedles(?string $raw, ?string $country = null): array
    {
        $needles = [];
        $digits = self::digits($raw);
        if (strlen($digits) >= 5) {
            $needles[] = $digits;
        }

        $e164 = self::toE164($raw, $country);
        if ($e164) {
            $e164Digits = self::digits($e164);
            if (strlen($e164Digits) >= 5) {
                $needles[] = $e164Digits;
            }
        }

        // Leading 8 → 375… is common in BY/RU paste without a country code
        // that libphonenumber can resolve; try default-country parse already
        // covered above. Also keep the last 9–10 digits for partial match.
        if (strlen($digits) >= 9) {
            $needles[] = substr($digits, -9);
            $needles[] = substr($digits, -10);
        }

        return array_values(array_unique(array_filter($needles, fn (string $n) => strlen($n) >= 5)));
    }
}
