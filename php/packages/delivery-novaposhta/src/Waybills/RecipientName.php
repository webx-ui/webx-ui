<?php

declare(strict_types=1);

namespace WebxUi\Delivery\NovaPoshta\Waybills;

/**
 * A name the carrier takes for a private recipient (§8.2): Ukrainian letters only — confirmed
 * «only in Ukrainian letters» — with an apostrophe in any of its three spellings, a hyphen and a
 * space inside a double name. No Russian `ё ъ ы э`, no Latin letters, no digits.
 *
 * Checked before any request, in the dialog: the manager fixes the name for this waybill only, the
 * order keeps what the buyer typed.
 */
final class RecipientName
{
    private const string LETTERS = 'АБВГҐДЕЄЖЗИІЇЙКЛМНОПРСТУФХЦЧШЩЬЮЯабвгґдеєжзиіїйклмнопрстуфхцчшщьюя';

    public static function valid(string $name): bool
    {
        $letters = '['.self::LETTERS.']';

        return preg_match("/^{$letters}+(?:['’ʼ\\- ]{$letters}+|{$letters})*\$/u", trim($name)) === 1;
    }
}
