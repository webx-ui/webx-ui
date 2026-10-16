<?php

declare(strict_types=1);

namespace WebxUi\Snippets\Checks;

use Illuminate\Contracts\Foundation\Application;
use WebxUi\Snippets\Places;
use WebxUi\Themes\Contracts\ThemeCheckPart;
use WebxUi\Themes\ThemeChain;

/**
 * The places in `webx:theme:check` (§10.1): every built-in place printed by some view the site
 * really reads, and every place of the config too. A layout that overrides theme-default's and
 * forgot `<x-webx-snippets place="head" />` loses the tag manager without looking broken.
 */
final class PlacesCheck implements ThemeCheckPart
{
    public function __construct(
        private readonly Places $places,
        private readonly PlaceFinder $finder,
        private readonly Application $app,
    ) {}

    public function check(ThemeChain $chain, array $viewPaths): array
    {
        $found = $this->finder->find($viewPaths);
        $layout = $found['layout'] ?? 'components/layout.blade.php';
        $lines = [];

        foreach ($this->places->keys() as $place) {
            if (isset($found['places'][$place])) {
                continue;
            }

            $lines[] = $this->places->builtIn($place)
                ? ['file' => $layout, 'line' => null, 'message' => "no <x-webx-snippets place=\"{$place}\">: codes of the place \"{$place}\" are never printed (SNIPPETS §4.1)."]
                : ['file' => $this->app->configPath('webx-snippets.php'), 'line' => null, 'message' => "place \"{$place}\" is declared but no view prints it: its codes are never printed (SNIPPETS §4.3)."];
        }

        foreach ($found['dynamic'] as $where) {
            $lines[] = ['file' => $where['file'], 'line' => $where['line'], 'message' => 'place set by an expression, not checked.', 'note' => true];
        }

        return $lines;
    }
}
