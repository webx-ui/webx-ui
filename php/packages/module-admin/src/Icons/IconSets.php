<?php

declare(strict_types=1);

namespace WebxUi\Admin\Icons;

use Closure;
use WebxUi\Admin\AdminServiceProvider;

/**
 * The icon sets a screen may name in `props.source` of a `wx-icon-picker`, by name.
 *
 * The one the frame registers is `theme`: the icons of the site's theme chain, the ones
 * `<x-theme::icon>` prints ({@see AdminServiceProvider}). A module or a site adds
 * its own with {@see register()}. A set is built when a screen asks for it, not when it is
 * registered: the theme's names come from files and its sprite's address from the last sync.
 */
final class IconSets
{
    /** @var array<string, Closure(): IconSet> */
    private array $sets = [];

    /**
     * @param  Closure(): IconSet  $set
     */
    public function register(string $source, Closure $set): void
    {
        $this->sets[$source] = $set;
    }

    public function has(string $source): bool
    {
        return isset($this->sets[$source]);
    }

    public function get(string $source): ?IconSet
    {
        if (! isset($this->sets[$source])) {
            return null;
        }

        // Not kept: the registry lives as long as the process, and a sync publishes a new sprite
        // under a new address while it runs.
        return ($this->sets[$source])();
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        $names = array_keys($this->sets);
        sort($names);

        return $names;
    }
}
