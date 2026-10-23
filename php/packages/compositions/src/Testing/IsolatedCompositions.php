<?php

declare(strict_types=1);

namespace WebxUi\Compositions\Testing;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\After;

/**
 * A compiled set of compositions a test of its own (COMPOSITIONS §6.2). Testbench shares one
 * storage between tests, and two tests that load other modules over the same files would read
 * each other's set: the sources are the same, what exists to compile against is not.
 *
 *     protected function defineEnvironment($app): void { $this->isolateCompositions($app); }
 */
trait IsolatedCompositions
{
    private ?string $isolatedCompositions = null;

    protected function isolateCompositions(Application $app): void
    {
        $this->isolatedCompositions = sys_get_temp_dir().'/webx-compositions-'.str_replace('.', '', uniqid('', true));
        $app['config']->set('webx-compositions.compiled', $this->isolatedCompositions);
    }

    #[After]
    protected function forgetIsolatedCompositions(): void
    {
        if ($this->isolatedCompositions !== null) {
            (new Filesystem)->deleteDirectory($this->isolatedCompositions);
            $this->isolatedCompositions = null;
        }
    }
}
