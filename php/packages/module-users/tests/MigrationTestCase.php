<?php

declare(strict_types=1);

namespace WebxUi\Users\Tests;

use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as Orchestra;
use WebxUi\Admin\AdminServiceProvider;
use WebxUi\Compositions\CompositionsServiceProvider;
use WebxUi\Localization\LocalizationServiceProvider;
use WebxUi\Themes\ThemeServiceProvider;
use WebxUi\Users\UsersServiceProvider;
use WebxUi\Widgets\WidgetsServiceProvider;

/**
 * The module over a database nothing has migrated yet: a test lays out what a site had before
 * and runs `migrate` itself.
 */
abstract class MigrationTestCase extends Orchestra
{
    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            ThemeServiceProvider::class,
            LocalizationServiceProvider::class,
            CompositionsServiceProvider::class,
            AdminServiceProvider::class,
            WidgetsServiceProvider::class,
            UsersServiceProvider::class,
        ];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('webx-localization.cache.enabled', false);
    }
}
