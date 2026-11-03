<?php

declare(strict_types=1);

namespace WebxUi\Payments;

use WebxUi\Payments\Types\BankTransfer;

/**
 * The bank details an order's block carries (spec §12.2), as the buyer reads them: in the order's
 * language, the purpose with the order's number. Shown while the order still awaits payment — on
 * «Thank you», the order's page and, later, the pay page and the letter.
 */
final class Instructions
{
    /**
     * @param  array<string, mixed>|null  $block  the order's `payment` block
     * @return array<string, string>|null field → text, in the order of `BankTransfer::FIELDS`; null — none
     */
    public static function of(?array $block, string $number, ?string $locale = null): ?array
    {
        $instructions = $block['instructions'] ?? null;

        if (! is_array($instructions)) {
            return null;
        }

        $lines = [];

        foreach (BankTransfer::FIELDS as $field) {
            $text = Words::pick(is_array($instructions[$field] ?? null) ? $instructions[$field] : null, $locale);

            if ($text !== null) {
                $lines[$field] = str_replace('{number}', $number, $text);
            }
        }

        return $lines === [] ? null : $lines;
    }

    /**
     * The details as plain text, one line a field — for a letter (§11.5).
     *
     * @param  array<string, mixed>|null  $block
     */
    public static function text(?array $block, string $number, ?string $locale = null): string
    {
        $lines = self::of($block, $number, $locale) ?? [];

        return implode("\n", array_map(
            static fn (string $field, string $text): string => trans('webx-payments::buyer.instructions.'.$field, [], $locale).': '.$text,
            array_keys($lines),
            $lines,
        ));
    }
}
