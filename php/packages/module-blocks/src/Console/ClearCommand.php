<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use WebxUi\Blocks\BlockTypes;
use WebxUi\Blocks\Regions;
use WebxUi\Blocks\Rendering\TemplateCompiler;

/**
 * Forget the registry's cache and drop the compiled templates.
 *
 * Neither is needed in the ordinary course of things — the registry forgets itself when a
 * version is saved or published, and a compiled file is named by its version — but a database
 * restored from a backup, or a cache shared between two installations, leaves both pointing at
 * what is no longer there.
 */
final class ClearCommand extends Command
{
    protected $signature = 'webx:blocks:clear';

    protected $description = 'Forget the cached block types and drop the compiled templates';

    public function handle(BlockTypes $types, TemplateCompiler $compiler, Filesystem $files, Regions $regions): int
    {
        $types->forget();
        $this->info('Block types forgotten.');

        // The regions' published trees too: a restored database leaves them just as stale.
        foreach (array_keys($regions->declared()) as $name) {
            $regions->forget($name);
        }

        $directory = $compiler->directory();

        if ($files->isDirectory($directory)) {
            $files->deleteDirectory($directory);
            $this->info("Compiled templates removed from {$directory}.");
        }

        return self::SUCCESS;
    }
}
