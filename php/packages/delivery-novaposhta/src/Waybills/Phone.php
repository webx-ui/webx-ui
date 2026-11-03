<?php

declare(strict_types=1);

namespace WebxUi\Delivery\NovaPoshta\Waybills;

/**
 * A Ukrainian phone as the carrier takes it: `380` and nine digits, nothing else (after the
 * documentation). «+38 (050) 123-45-67», «0501234567» and «380501234567» are one number.
 */
final class Phone
{
    public static function normalise(string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (strlen($digits) === 10 && str_starts_with($digits, '0')) {
            $digits = '38'.$digits;
        }

        return preg_match('/^380\d{9}$/', $digits) === 1 ? $digits : null;
    }
}
