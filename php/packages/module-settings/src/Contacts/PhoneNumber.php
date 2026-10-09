<?php

declare(strict_types=1);

namespace WebxUi\Settings\Contacts;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

/**
 * A number as somebody typed it — "+38 (044) 393-08-10" — and what a machine dials: E.164,
 * `+380443930810` (WIDGETS §12.1). The first is shown, the second goes into `tel:` and into the
 * markup.
 *
 * Only a number with its country code is a number. The country is not guessed from the site's
 * language: a language is not a country — Portuguese is Portugal and Brazil, English is half
 * the world — and a guess that is wrong is a link that rings somebody else. So a number without
 * one is refused when it is saved, with a word on what to add, rather than kept and dialled wrong.
 */
final class PhoneNumber
{
    public const string NO_COUNTRY = 'no-country';

    public const string INVALID = 'invalid';

    private function __construct(
        public readonly string $number,
        public readonly string $e164,
        public readonly string $href,
    ) {}

    /** The number, or null when it cannot be one — see `problem()` for why. */
    public static function parse(string $typed): ?self
    {
        $parsed = self::parsed($typed);

        if (! is_object($parsed)) {
            return null;
        }

        $util = PhoneNumberUtil::getInstance();

        return new self(
            trim($typed),
            $util->format($parsed, PhoneNumberFormat::E164),
            // RFC 3966 carries an extension (`;ext=12`), which E.164 has no room for.
            $util->format($parsed, PhoneNumberFormat::RFC3966),
        );
    }

    /** Why a typed number is refused: `no-country`, `invalid` — or null, it is a number. */
    public static function problem(string $typed): ?string
    {
        $parsed = self::parsed($typed);

        return is_string($parsed) ? $parsed : null;
    }

    /** The digits alone, for the links of messengers (`wa.me/<digits>`). */
    public function digits(): string
    {
        return ltrim($this->e164, '+');
    }

    private static function parsed(string $typed): \libphonenumber\PhoneNumber|string
    {
        $typed = trim($typed);

        // 00 is how most of the world dials out, but which prefix dials out is a country's
        // own: without a country to parse against, the library cannot know it means "+".
        if (str_starts_with($typed, '00')) {
            $typed = '+'.substr($typed, 2);
        }

        if (! str_starts_with($typed, '+')) {
            return preg_match('/\d/', $typed) === 1 ? self::NO_COUNTRY : self::INVALID;
        }

        $util = PhoneNumberUtil::getInstance();

        try {
            $parsed = $util->parse($typed, null);
        } catch (NumberParseException $exception) {
            return $exception->getCode() === NumberParseException::INVALID_COUNTRY_CODE ? self::NO_COUNTRY : self::INVALID;
        }

        // Possible, not valid: the ranges a country hands out change faster than the
        // library's data, and refusing a new real number is worse than keeping a typo's length.
        return $util->isPossibleNumber($parsed) ? $parsed : self::INVALID;
    }
}
