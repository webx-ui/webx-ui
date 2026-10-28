<?php

declare(strict_types=1);

namespace WebxUi\Users\Emails;

/**
 * An address as a page or a letter may show it to whoever looks over the visitor's shoulder:
 * `a…@example.com` — enough to recognise one's own, not enough to write to (spec §8.3, §11.6).
 */
final class Mask
{
    public static function email(string $email): string
    {
        $at = strrpos($email, '@');

        if ($at === false || $at === 0) {
            return '…';
        }

        return mb_substr($email, 0, 1).'…'.substr($email, $at);
    }
}
