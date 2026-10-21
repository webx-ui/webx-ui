<?php

declare(strict_types=1);

namespace WebxUi\Themes\Contracts;

use WebxUi\Themes\Sync\SyncLine;
use WebxUi\Themes\ThemeChain;

/**
 * Something `webx:theme:sync` brings up to date besides the built files of the layers — files
 * another package publishes from them.
 *
 * Not the site's data: the command runs on every deploy and every `composer update`, so a step
 * that wrote the database would overwrite what the site changed. The block types of the layers
 * left this contract for that reason (BLOCK_SCHEMAS §3) and are imported by a person instead.
 *
 * Tagged `webx-themes.sync` in the container. The engine does not know the packages that
 * register a step: a package below the themes knows the engine, never the other way round.
 */
interface SyncStep
{
    public const string TAG = 'webx-themes.sync';

    /** What the step syncs, as the heading of its lines. */
    public function title(): string;

    /**
     * One line per thing the step looked at. A refused line makes the command fail.
     *
     * @return list<SyncLine>
     */
    public function sync(ThemeChain $chain, bool $force, bool $dryRun): array;
}
