<?php

declare(strict_types=1);

namespace WebxUi\Admin\Icons;

/**
 * A set of icons a `wx-icon-picker` picks from: the names, and the sprite that draws them.
 *
 * Without a sprite the names are drawn by the panel's own `WxIcon`, so a set without one is a part
 * of the panel's set. The theme's has none until `webx:theme:sync` has published it once: the
 * names are still the theme's, and the grid draws what the panel has under them.
 */
final readonly class IconSet
{
    /**
     * @param  list<string>  $names
     */
    public function __construct(
        public array $names,
        public ?string $sprite = null,
    ) {}

    public function has(string $name): bool
    {
        return in_array($name, $this->names, true);
    }
}
