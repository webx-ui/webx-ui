<?php

declare(strict_types=1);

namespace WebxUi\Snippets\Checks;

use Illuminate\Contracts\Config\Repository as Config;
use WebxUi\Themes\ThemeChain;

/**
 * The directories the site's views are read from, in the order Blade reads them: the site's own
 * (`view.paths`), then the layers of the theme. What the doctor and `snippets://places` search.
 */
final class SiteViews
{
    public function __construct(
        private readonly Config $config,
        private readonly ThemeChain $chain,
    ) {}

    /**
     * @return list<string>
     */
    public function paths(): array
    {
        $site = array_filter((array) $this->config->get('view.paths', []), static fn (mixed $path): bool => is_string($path) && is_dir($path));

        return [...array_values(array_map(static fn (string $path): string => str_replace('\\', '/', $path), $site)), ...$this->chain->viewPaths()];
    }
}
