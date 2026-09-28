<?php

declare(strict_types=1);

namespace WebxUi\Blocks\View\Components;

use Closure;
use Illuminate\Container\Container;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\View\Component;
use WebxUi\Blocks\Regions;

/**
 * `<x-webx-blocks::region name="header" fallback="components.header" />` — a region of the layout.
 *
 * The site writes it around what it already prints, and nothing changes until the region is
 * published: the fallback is the markup from code, printed whenever the region has nothing to
 * show. `name` and `fallback` are the tag's own; every other attribute is data of the call — the
 * fallback view gets it as variables and `$attributes`, the blocks as `$region->data()` — so one
 * `:compact="true"` reaches both headers.
 *
 * No element of its own around the region: the frame — sticky, width, the shadow on scroll —
 * belongs to the layout, which puts it on the element the tag stands in (§2, decision 8).
 */
final class Region extends Component
{
    public function __construct(
        public string $name,
        public ?string $fallback = null,
    ) {}

    public function render(): Closure
    {
        return fn (): Htmlable => $this->draw();
    }

    /**
     * The closure straight to the view factory, which prints an `Htmlable` as it is — Laravel's
     * own resolver would read the returned markup as a view's name, and failing that compile it
     * as Blade a second time (the trap `BlockTag` documents).
     */
    public function resolveView(): Closure
    {
        return $this->render();
    }

    /**
     * The attributes are only known now: the compiled tag sets them after asking for the view.
     */
    private function draw(): Htmlable
    {
        $data = [];

        // Blade escapes every bound string for `{{ $attributes }}`; a value is a value, and it is
        // escaped where it is printed. The same unescaping `BlockTag` does.
        foreach ($this->attributes?->getAttributes() ?? [] as $key => $value) {
            $data[(string) $key] = is_string($value) ? htmlspecialchars_decode($value, ENT_QUOTES | ENT_HTML5) : $value;
        }

        return Container::getInstance()->make(Regions::class)->render($this->name, $data, $this->fallback);
    }
}
