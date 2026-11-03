<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase as Orchestra;
use WebxUi\Cache\CacheServiceProvider;
use WebxUi\Cart\CartServiceProvider;
use WebxUi\Cart\Money;
use WebxUi\Cart\PurchasableLine;
use WebxUi\Cart\Totals\Benefit;
use WebxUi\Cart\Totals\CartState;
use WebxUi\Cart\Totals\Purpose;
use WebxUi\Cart\Totals\ResolvedLine;
use WebxUi\Delivery\Delivery;
use WebxUi\Delivery\DeliveryContext;
use WebxUi\Delivery\DeliveryServiceProvider;
use WebxUi\Delivery\Models\DeliveryMethod;
use WebxUi\Delivery\Models\DeliveryPoint;
use WebxUi\Geography\Editor;
use WebxUi\Geography\GeographyServiceProvider;
use WebxUi\Geography\Models\GeoLocality;
use WebxUi\Geography\Models\GeoRegion;
use WebxUi\Localization\LocalizationServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        // Nothing here may reach a carrier: the delivery step reads the site's own tables (decision 38).
        Http::preventStrayRequests();
    }

    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            LocalizationServiceProvider::class,
            CacheServiceProvider::class,
            GeographyServiceProvider::class,
            CartServiceProvider::class,
            DeliveryServiceProvider::class,
        ];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('cache.default', 'array');
        $app['config']->set('webx-localization.locales', [['code' => 'en', 'default' => true], ['code' => 'pl']]);
        $app['config']->set('webx-localization.cache.enabled', false);
        $app['config']->set('webx-cart.currency', 'EUR');
        $app['config']->set('database.connections.testing.foreign_key_constraints', true);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->artisan('migrate')->run();
    }

    /** A region of `XX`, the private-use code: no test is tied to a real country (§18). */
    protected function region(string $name = 'Region A'): GeoRegion
    {
        $editor = $this->app->make(Editor::class);
        $editor->enableCountry('XX');

        return GeoRegion::query()->where('country_code', 'XX')->get()->first(fn (GeoRegion $region): bool => $region->name('en') === $name)
            ?? $editor->createRegion('XX', ['en' => $name]);
    }

    /** A locality made by hand, as an administrator does on a site without a single carrier (decision 42). */
    protected function town(string $name, string $region = 'Region A'): GeoLocality
    {
        return $this->app->make(Editor::class)->createLocality([
            'country' => 'XX',
            'region_id' => $this->region($region)->id,
            'kind' => 'town',
            'names' => ['en' => $name],
        ]);
    }

    /** @param  array<string, mixed>  $attributes */
    protected function method(string $key, string $type, array $attributes = []): DeliveryMethod
    {
        return DeliveryMethod::query()->create([
            'key' => $key,
            'type' => $type,
            'titles' => ['en' => ucfirst(str_replace('-', ' ', $key))],
            'enabled' => true,
            ...$attributes,
        ]);
    }

    /** @param  array<string, mixed>  $attributes */
    protected function point(GeoLocality $town, string $kind = 'store', array $attributes = []): DeliveryPoint
    {
        return DeliveryPoint::query()->create([
            'kind' => $kind,
            'locality_id' => $town->id,
            'titles' => ['en' => 'Store A'],
            'address' => ['en' => '1 Example street'],
            'phone' => '+800 1234 5678',
            ...$attributes,
        ]);
    }

    /**
     * A counted cart: each line [shippable, available, price], the benefits by key.
     *
     * @param  list<array{0: bool, 1?: bool, 2?: int}|null>  $lines  null — a line whose record is gone
     * @param  list<string>  $benefits
     */
    protected function cart(array $lines = [[true]], array $benefits = []): CartState
    {
        $resolved = [];
        $subtotal = 0;

        foreach ($lines as $index => $spec) {
            $price = $spec[2] ?? 1000;
            $available = $spec === null ? false : ($spec[1] ?? true);
            $line = $spec === null ? null : new PurchasableLine(
                name: 'Item '.$index,
                sku: null,
                price: $price,
                currency: 'EUR',
                shippable: $spec[0],
                available: $available,
            );
            $resolved[] = new ResolvedLine($index + 1, 'test.product', $index + 1, 1, 1, null, $line, null, null, 'Item '.$index, $line === null ? null : $price, $price, $available);
            $subtotal += $available ? $price : 0;
        }

        return new CartState(
            currency: 'EUR',
            locale: 'en',
            lines: $resolved,
            lineAdjustments: [],
            subtotal: $subtotal,
            adjustments: [],
            total: $subtotal,
            tenders: [],
            payable: $subtotal,
            benefits: array_map(static fn (string $key): Benefit => new Benefit($key, $key, 'test'), $benefits),
            hints: [],
            warnings: [],
            money: $this->app->make(Money::class),
        );
    }

    protected function context(GeoLocality $town, ?CartState $cart = null, Purpose $purpose = Purpose::Checkout): DeliveryContext
    {
        return $this->app->make(Delivery::class)->context($cart ?? $this->cart(), $town, $purpose);
    }
}
