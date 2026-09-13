<?php

declare(strict_types=1);

namespace WebxUi\Media\Tests;

use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as Orchestra;
use WebxUi\Admin\AdminServiceProvider;
use WebxUi\Localization\LocalizationServiceProvider;
use WebxUi\Media\MediaServiceProvider;
use WebxUi\NestedSet\NestedSetServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            LocalizationServiceProvider::class,
            NestedSetServiceProvider::class,
            AdminServiceProvider::class,
            MediaServiceProvider::class,
        ];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('database.default', 'testing');

        // The dictionary is cached in a real installation; a test that reads it wants the file
        // it just wrote, not what the previous test left behind.
        $app['config']->set('webx-localization.cache.enabled', false);
    }
}
