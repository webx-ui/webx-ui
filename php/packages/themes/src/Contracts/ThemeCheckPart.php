<?php

declare(strict_types=1);

namespace WebxUi\Themes\Contracts;

use WebxUi\Themes\ThemeChain;

/**
 * Something `webx:theme:check` holds the chain to besides what the engine knows — the places of
 * `webx-ui/module-snippets`, which a layout that overrides theme-default's can lose (spec §17).
 *
 * Tagged `webx-themes.check` in the container, the way a `SyncStep` is: the engine does not know
 * the packages that register a part, so a site without them is checked as it always was.
 *
 * A finding is a refusal like the engine's own — the command fails on it. A note is a line said
 * and not counted: what the part could not check, and why.
 */
interface ThemeCheckPart
{
    public const string TAG = 'webx-themes.check';

    /**
     * `$viewPaths` are the directories of views in the order Blade looks them up — the site's
     * own first when the site is checked, only the chain's when a theme is checked by its path.
     *
     * @param  list<string>  $viewPaths
     * @return list<array{file: string, line: int|null, message: string, note?: bool}>
     */
    public function check(ThemeChain $chain, array $viewPaths): array;
}
