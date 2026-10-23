<?php

declare(strict_types=1);

namespace WebxUi\Compositions\Console;

use Illuminate\Console\Command;
use Throwable;
use WebxUi\Compositions\Compositions;
use WebxUi\Compositions\Finding;

/**
 * Compiles every composition × handle (COMPOSITIONS §6.2) — the step of `webx:boot` before
 * `view:cache`. A tree that cannot be built fails the command and leaves the last set serving.
 */
final class CompileCommand extends Command
{
    protected $signature = 'webx:composition:compile';

    protected $description = 'Compile the compositions (layout and the others) into Blade views';

    public function handle(Compositions $compositions): int
    {
        try {
            $manifest = $compositions->compile();
        } catch (Throwable $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $views = (array) $manifest['views'];
        $count = array_sum(array_map(static fn (mixed $handles): int => count((array) $handles), $views));
        $this->components->info(sprintf('Compiled %d %s of %d %s.', $count, $count === 1 ? 'view' : 'views', count($views), count($views) === 1 ? 'composition' : 'compositions'));

        foreach ((array) $manifest['findings'] as $finding) {
            $this->components->warn((string) Finding::fromArray($finding));
        }

        return self::SUCCESS;
    }
}
