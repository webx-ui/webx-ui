<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Variables;

use WebxUi\Catalog\Models\Product;

/**
 * Whether a barcode is a GTIN a marketplace may be given (§9.1 of the feeds spec): 8, 12, 13 or 14
 * digits, the check digit right, spaces and hyphens aside, and not from a range that was never
 * meant to leave the shop: 02, 04, 2 are internal numbers, 05, 98, 99 coupons.
 *
 * In doubt it answers no: a made-up identifier costs more at a marketplace than none.
 */
final class Gtin
{
    private const LENGTHS = [8, 12, 13, 14];

    private const REFUSED = ['02', '04', '05', '2', '98', '99'];

    /** The digits of the GTIN, null when this is not one. */
    public static function of(?string $barcode): ?string
    {
        $digits = (string) preg_replace('/[\s-]+/', '', (string) $barcode);

        if (preg_match('/^\d+$/', $digits) !== 1 || ! in_array(strlen($digits), self::LENGTHS, true) || trim($digits, '0') === '') {
            return null;
        }

        return self::checks($digits) && ! self::refused($digits) ? $digits : null;
    }

    /** The GTIN of the product's main barcode, null on a site without barcodes. */
    public static function main(Product $product): ?string
    {
        return config('webx-catalog.fields.barcode', true) ? self::of($product->barcode) : null;
    }

    private static function checks(string $digits): bool
    {
        $sum = 0;
        $weight = 3;

        // From the digit before the check digit leftwards, which is the same at every length.
        for ($at = strlen($digits) - 2; $at >= 0; $at--) {
            $sum += (int) $digits[$at] * $weight;
            $weight = 4 - $weight;
        }

        return (10 - $sum % 10) % 10 === (int) $digits[strlen($digits) - 1];
    }

    /**
     * The ranges are those of a GTIN-13: a UPC is one with a zero in front, and a GTIN-14 one with
     * a packaging digit in front. An EAN-8 has no longer form and is read as written.
     */
    private static function refused(string $digits): bool
    {
        $number = strlen($digits) === 8 ? $digits : substr(str_pad($digits, 14, '0', STR_PAD_LEFT), 1);

        foreach (self::REFUSED as $prefix) {
            if (str_starts_with($number, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
