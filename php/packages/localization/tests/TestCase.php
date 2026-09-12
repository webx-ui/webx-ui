<?php

declare(strict_types=1);

namespace WebxUi\Localization\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use WebxUi\Localization\Locales;
use WebxUi\Localization\LocalizationServiceProvider;
use WebxUi\Localization\Models\Locale;

abstract class TestCase extends Orchestra
{
    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [LocalizationServiceProvider::class];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        // Reading a cached list is the thing most likely to make one test answer with another
        // test's languages, and every test here is about what the list says.
        $app['config']->set('webx-localization.cache.enabled', false);
        $app['config']->set('webx-localization.locales', [
            ['code' => 'en', 'default' => true],
            ['code' => 'uk'],
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->artisan('migrate')->run();

        Schema::create('pages', function (Blueprint $table): void {
            $table->id();
            $table->json('title')->nullable();
            $table->json('body')->nullable();
            $table->string('code')->nullable();
            $table->timestamps();
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function locale(string $code, array $attributes = []): Locale
    {
        $locale = Locale::fromCode($code, $attributes);
        $locale->save();

        app(Locales::class)->forget();

        return $locale;
    }
}
