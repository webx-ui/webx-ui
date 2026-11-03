<?php

declare(strict_types=1);

namespace WebxUi\Geography\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase as Orchestra;
use WebxUi\Geography\GeographyServiceProvider;
use WebxUi\Geography\Models\GeoCountry;
use WebxUi\Geography\Models\GeoLocality;
use WebxUi\Geography\Models\GeoRegion;
use WebxUi\Localization\LocalizationServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        // A test that reaches the network waits on it; whatever it calls is faked.
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
            GeographyServiceProvider::class,
        ];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('webx-localization.locales', [
            ['code' => 'en', 'default' => true],
            ['code' => 'de'],
        ]);
        $app['config']->set('webx-localization.cache.enabled', false);
        // Cascades and restrictions are part of what is tested; sqlite keeps them only when asked.
        $app['config']->set('database.connections.testing.foreign_key_constraints', true);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->artisan('migrate')->run();
    }

    /** `XX` is for private use: a test is tied to no real country (§18). */
    protected function country(string $code = 'XX', bool $active = true): GeoCountry
    {
        return GeoCountry::query()->firstOrCreate(['code' => $code], ['active' => $active]);
    }

    protected function region(string $name, string $country = 'XX'): GeoRegion
    {
        $this->country($country);

        return GeoRegion::query()->create(['country_code' => $country, 'names' => ['en' => $name]]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function locality(string $name, array $attributes = []): GeoLocality
    {
        $this->country($attributes['country_code'] ?? 'XX');

        return GeoLocality::query()->create([
            'country_code' => 'XX',
            'kind' => 'town',
            'names' => ['en' => $name],
            'created_by' => 'carrier:carrier-a',
            ...$attributes,
        ]);
    }
}
