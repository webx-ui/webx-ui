<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Tests;

use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Catalog\Variables\ProductVariables;

/**
 * The registry on a catalogue without satellites, prices or part numbers: a template written for a
 * full shop still knows every name, and the values are empty rather than missing.
 */
final class ProductVariablesCoreTest extends TestCase
{
    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('webx-catalog.price.enabled', false);
        $app['config']->set('webx-catalog.fields.mpn', false);
        $app['config']->set('webx-catalog.fields.barcode', false);
    }

    #[Test]
    public function what_is_switched_off_is_empty_and_availability_has_its_fallback(): void
    {
        $product = $this->product('Pads', $this->category('brakes'), ['slug' => 'pads']);
        $row = $this->app->make(ProductVariables::class)->values(collect([$product]), 'en')[$product->id];

        $this->assertSame('in_stock', $row['availability']);
        $this->assertSame('', $row['price']);
        $this->assertSame('', $row['price_with_currency']);
        $this->assertSame('', $row['mpn']);
        $this->assertSame('', $row['gtin']);
        $this->assertSame([], $row['gtins']);
        $this->assertSame([], $row['barcodes']);
        $this->assertSame('no', $row['identifier_exists']);
        $this->assertSame('', $row['brand']);
    }
}
