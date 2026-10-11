<?php

declare(strict_types=1);

namespace WebxUi\Themes\Contracts;

use WebxUi\Themes\Sync\SyncLine;
use WebxUi\Themes\ThemeChain;

/**
 * Something `webx:theme:sync` brings up to date besides the built files — the block types of
 * the layers, put on the site by `webx-ui/module-blocks` (spec §9.2).
 *
 * Tagged `webx-themes.sync` in the container. The engine does not know the packages that
 * register a step: a package below the themes knows the engine, never the other way round, so
 * a site without the blocks module syncs its assets and nothing else.
 */
interface SyncStep
{
    public const string TAG = 'webx-themes.sync';

    /** What the step syncs, as the heading of its lines: «Block types». */
    public function title(): string;

    /**
     * One line per thing the step looked at. A refused line makes the command fail; a kept one
     * does not — it is the site's own work, left alone on purpose.
     *
     * @return list<SyncLine>
     */
    public function sync(ThemeChain $chain, bool $force, bool $dryRun): array;
}
