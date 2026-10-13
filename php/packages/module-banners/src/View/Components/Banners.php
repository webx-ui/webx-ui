<?php

declare(strict_types=1);

namespace WebxUi\Banners\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Component;
use WebxUi\Banners\Places;

/**
 * `<x-webx-banners place="hero" />` — a place whole, in its layout (§4.3 of the layers spec).
 *
 * Its width is its parent's and its height the place's `ratio`, so it is the whole first screen
 * or one column of it alike. `slider` is `<x-webx-slider variant="hero">` of the widgets on a site
 * with a theme, else a strip of its own that snaps, plays and has arrows and dots; `single` is the first banner; `random` all of
 * them, all but the first hidden, and a line of script opens one at random. An empty place prints
 * nothing at all — not even the wrapper — so a template needs no `@if`.
 *
 * Attributes given to it land on the wrapper: a theme's hero puts its own class there.
 */
final class Banners extends Component
{
    /** @var list<array<string, mixed>> */
    public array $banners;

    /** @var array<string, mixed> */
    public array $options;

    public bool $overflow;

    public bool $slider;

    public function __construct(
        Places $places,
        public string $place = '',
        ?string $layout = null,
        public ?string $label = null,
    ) {
        $this->options = banners_layout($place, $layout);
        $this->banners = $place === '' ? [] : banners($place)->get();
        $this->overflow = $places->overflows($place);

        if ($this->options['layout'] === 'single') {
            $this->banners = array_slice($this->banners, 0, 1);
        }

        $this->slider = $this->options['layout'] === 'slider'
            && count($this->banners) > 1
            && array_key_exists('webx-slider', Blade::getClassComponentAliases())
            && self::themed();
    }

    /**
     * Whether the page wears a theme: the widgets' slider is styled by it, and without one it is
     * a strip of unstyled controls — the place's own strip is better there.
     */
    private static function themed(): bool
    {
        $chain = 'WebxUi\\Themes\\ThemeChain';

        return app()->bound($chain) && ! app($chain)->isEmpty();
    }

    public function shouldRender(): bool
    {
        return $this->banners !== [];
    }

    public function render(): View
    {
        return view('webx-banners::components.banners');
    }
}
