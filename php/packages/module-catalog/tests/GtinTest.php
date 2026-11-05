<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WebxUi\Catalog\Variables\Gtin;

final class GtinTest extends TestCase
{
    /**
     * @return array<string, array{0: string|null, 1: string|null}>
     */
    public static function barcodes(): array
    {
        return [
            'EAN-13' => ['4006381333931', '4006381333931'],
            'spaces and hyphens' => ['400-6381 333931', '4006381333931'],
            'UPC-A' => ['036000291452', '036000291452'],
            'EAN-8' => ['96385074', '96385074'],
            'GTIN-14' => ['14006381333938', '14006381333938'],
            'a wrong check digit' => ['4006381333932', null],
            'a wrong length' => ['400638133393', null],
            'letters' => ['WX-9001', null],
            'nothing' => [null, null],
            'zeros' => ['0000000000000', null],
            'internal, 2' => ['2000000000008', null],
            'internal, 02' => ['0212345678900', null],
            'internal, 04' => ['0412345678908', null],
            'coupon, 05' => ['0512345678907', null],
            'coupon, 99' => ['9912345678900', null],
            'internal, as a UPC' => ['212345678900', null],
        ];
    }

    #[Test]
    #[DataProvider('barcodes')]
    public function a_barcode_is_a_gtin_only_when_it_can_be_one(?string $barcode, ?string $gtin): void
    {
        $this->assertSame($gtin, Gtin::of($barcode));
    }
}
