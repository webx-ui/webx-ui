<?php

declare(strict_types=1);

namespace WebxUi\Debug\Tests;

use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\ModuleRegistry;

/**
 * A site before `key:generate` — `webx:setup` on a fresh copy, static analysis booting the
 * framework — still starts: the bar takes the encrypter only when a token is made or read.
 */
final class WithoutKeyTest extends TestCase
{
    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('app.key', null);
    }

    #[Test]
    public function the_application_boots(): void
    {
        $this->assertNull(config('app.key'));
        $this->assertTrue($this->app->make(ModuleRegistry::class)->has('debug'));
    }
}
