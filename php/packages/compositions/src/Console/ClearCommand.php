<?php

declare(strict_types=1);

namespace WebxUi\Compositions\Console;

use Illuminate\Console\Command;
use WebxUi\Compositions\Compiler;

/** Removes the compiled set; the next request or `webx:composition:compile` builds it again. */
final class ClearCommand extends Command
{
    protected $signature = 'webx:composition:clear';

    protected $description = 'Remove the compiled compositions';

    public function handle(Compiler $compiler): int
    {
        $compiler->clear();
        $this->components->info('Compiled compositions cleared.');

        return self::SUCCESS;
    }
}
