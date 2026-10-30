<?php

declare(strict_types=1);

namespace WebxUi\Orders\Checkout;

/**
 * The contacts of a draft as an agent sees them (spec §32): the initials, the code of the country
 * and the last two digits of the phone, the first letter and the domain of the e-mail. Enough to
 * tell two drafts apart or to recognise a buyer who wrote in, not enough to call or write to
 * somebody who placed no order. The contacts of an order are `orders_get`.
 */
final class ContactMask
{
    /** `Buyer Aster` → `B*** A`; null — no name. */
    public static function name(?string $first, ?string $last = null): ?string
    {
        $first = self::initial($first);
        $last = self::initial($last);

        if ($first === null) {
            return $last;
        }

        return $first.'***'.($last !== null ? ' '.$last : '');
    }

    /**
     * `+800 1234 5678` → `+800 *** **78`: the code of the country as typed, the last two digits,
     * the rest stars. A phone of four digits or fewer is all stars.
     */
    public static function phone(?string $phone): ?string
    {
        $phone = trim((string) $phone);
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return null;
        }

        if (strlen($digits) <= 4) {
            return '***';
        }

        $code = preg_match('/^\+(\d{1,4})[\s().\-]/', $phone, $match) === 1 ? '+'.$match[1].' ' : '';

        return $code.'*** **'.substr($digits, -2);
    }

    /** `buyer@example.com` → `b***@example.com`. */
    public static function email(?string $email): ?string
    {
        $email = trim((string) $email);
        $at = strrpos($email, '@');

        if ($at === false || $at === 0) {
            return $email === '' ? null : '***';
        }

        return mb_substr($email, 0, 1).'***'.substr($email, $at);
    }

    /**
     * A contact of the draft (`customer`, `recipient`) masked field by field.
     *
     * @param  array<string, mixed>|null  $contact
     * @return array{name: string|null, phone: string|null, email: string|null}|null
     */
    public static function contact(?array $contact): ?array
    {
        if ($contact === null) {
            return null;
        }

        $text = static fn (string $key): ?string => is_scalar($contact[$key] ?? null) ? (string) $contact[$key] : null;

        return [
            'name' => self::name($text('first_name'), $text('last_name')),
            'phone' => self::phone($text('phone')),
            'email' => self::email($text('email')),
        ];
    }

    private static function initial(?string $word): ?string
    {
        $word = trim((string) $word);

        return $word === '' ? null : mb_strtoupper(mb_substr($word, 0, 1));
    }
}
